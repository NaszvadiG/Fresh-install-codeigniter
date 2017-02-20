<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends MY_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */
	
	
	
	public function register_user($auth_level = 1)
	{
		//`user_id`, `username`, `email`, `auth_level`, `banned`, `passwd`, `passwd_recovery_code`,
		//`passwd_recovery_date`, `passwd_modified_at`, `last_login`, `created_at`, `modified_at
		$user_data = array(
			'passwd'    	 => "123123123",
			'email'    		 => "admin@admin.com",
			'auth_level'     => "9"	
		);

		$profile_data = array(
			"firstname" 	=> "System",
			"lastname"		=> "Admin",
		);

		$user_id = $this->Admin_model->register_user($user_data, $profile_data);
	}
	
	
	//This function will make the login
	public function login()
	{
		// Method should not be directly accessible
        if( $this->uri->uri_string() == 'users /login')
            show_404();

        if( strtolower( $_SERVER['REQUEST_METHOD'] ) == 'post' )
            $this->require_min_level(1);

        $this->setup_login_form();
	
        echo $this->load->view('login_view', '', TRUE);
        
	}
	
	public function logout()
    {
        $this->authentication->logout();

        // Set redirect protocol
        $redirect_protocol = USE_SSL ? 'https' : NULL;

        redirect( site_url( LOGIN_PAGE . '?logout=1', $redirect_protocol ) );
    }
	
	

	//Send email here to generate recovery
	public function recover()
    {
		
        // Load resources
        $this->load->model('Admin_model');
		$recover = "?recover=";
        /// If IP or posted email is on hold, display message
        if( $on_hold = $this->authentication->current_hold_status( TRUE ) )
        {
            $view_data['disabled'] = 1;
        }
        else
        {
            // If the form post looks good
            if( /*$this->tokens->match &&*/ $this->input->post('email') )
            {
				//echo "Token and email match<br/>";
                if( $user_data = $this->Admin_model->get_recovery_data( $this->input->post('email') ) )
                {
					//echo "Email Found<br/>";
                    // Check if user is banned
                    if( $user_data->banned == '1' )
                    {
                        // Log an error if banned
                        $this->authentication->log_error( $this->input->post('email', TRUE ) );

                        // Show special message for banned user
                        $view_data['banned'] = 1;
                    }
                    else
                    {
                        /**
                         * Use the authentication libraries salt generator for a random string
                         * that will be hashed and stored as the password recovery key.
                         * Method is called 4 times for a 88 character string, and then
                         * trimmed to 72 characters
                         */
                        $recovery_code = substr( $this->authentication->random_salt() 
                            . $this->authentication->random_salt() 
                            . $this->authentication->random_salt() 
                            . $this->authentication->random_salt(), 0, 72 );

                        // Update user record with recovery code and time
                        $this->Admin_model->update_user_raw_data(
                            $user_data->user_id,
                            [
                                'passwd_recovery_code' => $this->authentication->hash_passwd($recovery_code),
                                'passwd_recovery_date' => date('Y-m-d H:i:s')
                            ]
                        );

						//echo "Code: ".$recovery_code."<br/>";
						
                        // Set the link protocol
                        $link_protocol = USE_SSL ? 'https' : NULL;

                        // Set URI of link
                        $link_uri = 'users/recovery_verification/' . $user_data->user_id . '/' . $recovery_code;

                        $view_data['special_link'] = anchor( 
                            site_url( $link_uri, $link_protocol ), 
                            site_url( $link_uri, $link_protocol ), 
                            'target ="_blank"' 
                        );

						$url = site_url($link_uri, $link_protocol);
						
// 						$this->load->model("Email_model");
// 						$this->Email_model->reset_password( $this->input->post('email', TRUE ), $url);
						
						//echo "Special Link: ".$view_data['special_link']."<br/>";
						
                        $view_data['confirmation'] = 1;
						$recover .= "1";
                    }
                }

                // There was no match, log an error, and display a message
                else
                {
                    // Log the error
                    $this->authentication->log_error( $this->input->post('email', TRUE ) );
					//echo "Email not found";
                    $view_data['no_match'] = 1;
					
					$recover .= "0";
                }
            }
        }

        echo $this->load->view('examples/page_header', '', TRUE);

        echo $this->load->view('examples/recover_form', ( isset( $view_data ) ) ? $view_data : '', TRUE );

        echo $this->load->view('examples/page_footer', '', TRUE);
		
// 		redirect("login".$recover."#reminder");
		
    }

	
    // --------------------------------------------------------------

    /**
     * Verification of a user by email for recovery
     * 
     * @param  int     the user ID
     * @param  string  the passwd recovery code
     */
    public function recovery_verification( $user_id = '', $recovery_code = '' )
    {
        /// If IP is on hold, display message
        if( $on_hold = $this->authentication->current_hold_status( TRUE ) )
        {
            $view_data['disabled'] = 1;
        }
        else
        {
            // Load resources
            $this->load->model('Admin_model');

            if( 
                /**
                 * Make sure that $user_id is a number and less 
                 * than or equal to 10 characters long
                 */
                is_numeric( $user_id ) && strlen( $user_id ) <= 10 &&

                /**
                 * Make sure that $recovery code is exactly 72 characters long
                 */
                strlen( $recovery_code ) == 72 &&

                /**
                 * Try to get a hashed password recovery 
                 * code and user salt for the user.
                 */
                $recovery_data = $this->Admin_model->get_recovery_verification_data( $user_id ) )
            {
                /**
                 * Check that the recovery code from the 
                 * email matches the hashed recovery code.
                 */
// 				echo "Data OK<br/>";
                if( $recovery_data->passwd_recovery_code == $this->authentication->check_passwd( $recovery_data->passwd_recovery_code, $recovery_code ) )
                {
                    $view_data['user_id']       = $user_id;
                    $view_data['username']      = $recovery_data->username;
                    $view_data['recovery_code'] = $recovery_data->passwd_recovery_code;
// 					echo "Link OK<br/>";
                }

                // Link is bad so show message
                else
                {
                    $view_data['recovery_error'] = 1;

// 					echo $recovery_data->passwd_recovery_code."<br/>";
					//echo $recovery_code."<br/>";
					
					
                    // Log an error
                    $this->authentication->log_error('');
					//echo "LINK BAD<br/>";
                }
            }

            // Link is bad so show message
            else
            {
                $view_data['recovery_error'] = 1;
				//echo "Link bad error here<br/>";
                // Log an error
                $this->authentication->log_error('');
            }

            /**
             * If form submission is attempting to change password 
             */
            if( $this->tokens->match )
            {
                $this->Admin_model->recovery_password_change();
            }
        }

        echo $this->load->view('examples/page_header', '', TRUE);

        echo $this->load->view( 'examples/choose_password_form', $view_data, TRUE );

        echo $this->load->view('examples/page_footer', '', TRUE);
		
// 		var_dump($view_data);
		
		//echo $this->load->view('password_reset_view', $view_data, TRUE);
    }

    // --------------------------------------------------------------
	
}
