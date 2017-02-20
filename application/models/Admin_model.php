<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_model extends CI_Model{
	
	
	
	public function getUsers($search_data)
	{
		//Get from users, user_groups, profile
		$keyword="";
		$key=$search_data["keyword"];
		$d1=$search_data['start_date'];
		$d2=$search_data['end_date'];
		
		$date1="";
		$date2="";
		
		if($d1!=""){
			$date1=" AND u.created_on>=".strtotime($d1);
		}
		if($d2!=""){
			$date2=" AND u.created_on<=".strtotime($d2);
		}
		
		
		if($key!=""){
			$keyword="  AND ( u.email like '%$key%' 
						OR p.firstname like '%$key%'
						OR p.lastname like '%$key%'
						OR p.phone like '%$key%'
						OR  p.hotel_name like '%$key%'   ) ";
			
		}
		
		$str="Select * FROM users as u
			inner join profile as p on p.user_id=u.user_id
			WHERE u.auth_level=1  ".$keyword.$date1.$date2;
		
		$users = $this->db->query($str);
						
		if($users->num_rows() > 0)
			return $users->result_array();
		else return false;
				
		//Where profile.users_id = users.id
		//where users_group.group_id = 2
		//where users_group.user_id = users.id
	}
	
	public function getAdmins()
	{
		//Get from users, user_groups, profile
		
		
		$str="Select * FROM users as u
			left join profile as p on p.user_id=u.user_id
			WHERE u.auth_level=9";
		
		$users = $this->db->query($str);
						
		if($users->num_rows() > 0)
			return $users->result_array();
		else return false;
	}
	
	public function register_user($user_data, $profile_data)
	{
		/*$user_data = array(
			'user_name'     => 'user1',
			'user_pass'     => '123456',
			'user_email'    => 'test1@user.com',
			'user_level'    => 1,
			'user_id'       => $this->_get_unused_id(),
			'user_salt'     => $this->authentication->random_salt(),
			'user_date'     => time(),
			'user_modified' => time()
			
			        'user_name'     => $this->input->post('name', True),
              'user_pass'     => $this->input->post('password', True),
              'user_email'    => $this->input->post('email', True),
        			'profile_name'  => $this->input->post('name', True),
        			'profile_surname'  => $this->input->post('surname', True),
              'profile_country'  => $this->input->post('country', True),
              'profile_city'  => $this->input->post('city', True),
        			'user_level'    => 1
					
		`user_id`, `username`, `email`, `auth_level`, `banned`, `passwd`, `passwd_recovery_code`,
		`passwd_recovery_date`, `passwd_modified_at`, `last_login`, `created_at`, `modified_at	
		);*/
		
		$user_data['user_id']       = $this->get_unused_id();
		$user_data['modified_at'] = time();
	    $user_data['created_at'] 	= date('Y-m-d H:i:s');
		
		
		$user_data['username'] = $this->generate_username($profile_data['firstname'], $profile_data['lastname']);
		
		$user_data['passwd'] = $this->authentication->hash_passwd( $user_data['passwd'] );

    	$profile_data['user_id'] = $user_data['user_id'];

		$this->db->set($user_data)
			->insert( config_item('user_table'));

		if( $this->db->affected_rows() == 1 )
		{
		   $this->db->set($profile_data)
		    ->insert( 'profile' );
		}
    
		return $user_data['user_id'];
	}
	
	public function add_logo_path($path, $user_id)
	{
		$this->db->where("user_id", $user_id)
			->update('profile', array('logo' => $path));
	}
	
	public function edit_user($user_data, $profile_data, $user_id)
	{
		$user_data['username'] = $this->generate_username($profile_data['firstname'], $profile_data['lastname']);
		$user_data['modified_at'] = time();
		
		$this->update_user_raw_data($user_id, $user_data);
		
		$this->db->where('user_id', $user_id)
			->update( 'profile', $profile_data );
	}
	
	public function email_exists($email)
	{
		$email = $this->db->select('email')
						->from('users')
						->where('email', $email)
						->get();
		
		if($email->num_rows() > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
		
	}
	
	public function get_user_data($id)
	{
		$user = $this->db->select('*')
						->from('profile, users')
						->where('profile.user_id', $id)
						->where('users.user_id', $id)
						->limit(1)
						->get();
		
		return $user->result()[0];
	}
	
  //---------------------------------------------------------------
  // Nertil's Code - Updated for events
  //---------------------------------------------------------------
  public function generate_username($name, $surname)
  {
    $generated_name = strtolower($name[0]).strtolower($surname);
    
    //Check if the username already exists
    $this->db->select('user_id');
    $this->db->from('users');
    $this->db->where("username like '".$generated_name."%'");
    
    $query = $this->db->get();
    
    $res = $query->result_array();
    
    if(sizeof($res) > 0)
    {
      $generated_name = $generated_name.(sizeof($res)+1);
    }

    return $generated_name;
    
  }
	
	/**
     * Get an unused ID for user creation
     *
     * @return  int between 1200 and 4294967295
     */
    public function get_unused_id()
    {
        // Create a random user id between 1200 and 4294967295
        $random_unique_int = 2147483648 + mt_rand( -2147482448, 2147483647 );

        // Make sure the random user_id isn't already in use
        $query = $this->db->where( 'user_id', $random_unique_int )
            ->get_where( config_item('user_table') );

        if( $query->num_rows() > 0 )
        {
            $query->free_result();

            // If the random user_id is already in use, try again
            return $this->get_unused_id();
        }

        return $random_unique_int;
    }

    // --------------------------------------------------------------
	
	
	/*
		Auth Functions (copied from official model)
	*/
	/**
	 * Update a user record with data not from POST
	 *
	 * @param  int     the user ID to update
	 * @param  array   the data to update in the user table
	 * @return bool
	 */
	public function update_user_raw_data( $the_user, $user_data = [] )
	{
		$this->db->where('user_id', $the_user)
			->update( config_item('user_table'), $user_data );
	}

	// --------------------------------------------------------------

	/**
	 * Get data for a recovery
	 * 
	 * @param   string  the email address
	 * @return  mixed   either query data or FALSE
	 */
	public function get_recovery_data( $email )
	{
		$query = $this->db->select( 'u.user_id, u.email, u.banned' )
			->from( config_item('user_table') . ' u' )
			->where( 'LOWER( u.email ) =', strtolower( $email ) )
			->limit(1)
			->get();

		if( $query->num_rows() == 1 )
			return $query->row();

		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Get the user name, user salt, and hashed recovery code,
	 * but only if the recovery code hasn't expired.
	 *
	 * @param  int  the user ID
	 */
	public function get_recovery_verification_data( $user_id )
	{
		$recovery_code_expiration = date('Y-m-d H:i:s', time() - config_item('recovery_code_expiration') );

		$query = $this->db->select( 'username, passwd_recovery_code' )
			->from( config_item('user_table') )
			->where( 'user_id', $user_id )
			->where( 'passwd_recovery_date >', $recovery_code_expiration )
			->limit(1)
			->get();

		if ( $query->num_rows() == 1 )
			return $query->row();
		
		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Validation and processing for password change during account recovery
	 */
	public function recovery_password_change()
	{
		$this->load->library('form_validation');

		// Load form validation rules
		$this->load->model('validation_callables');
		$this->form_validation->set_rules([
			[
				'field' => 'passwd',
				'rules' => [
					'trim',
					'required',
					'matches[passwd_confirm]'
				]
			],
			[
				'field' => 'passwd_confirm',
				'rules' => 'trim|required'
			],
			[
				'field' => 'recovery_code'
			],
			[
				'field' => 'user_identification'
			]
		]);

		if( $this->form_validation->run() !== FALSE )
		{
			$this->load->vars( ['validation_passed' => 1] );

			$this->_change_password(
				set_value('passwd'),
				set_value('passwd_confirm'),
				set_value('user_identification'),
				set_value('recovery_code')
			);
		}
		else
		{
			$this->load->vars( ['validation_errors' => validation_errors()] );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Change a user's password
	 * 
	 * @param  string  the new password
	 * @param  string  the new password confirmed
	 * @param  string  the user ID
	 * @param  string  the password recovery code
	 */
	protected function _change_password( $password, $password2, $user_id, $recovery_code )
	{
		// User ID check
		if( isset( $user_id ) && $user_id !== FALSE )
		{
			$query = $this->db->select( 'user_id' )
				->from( config_item('user_table') )
				->where( 'user_id', $user_id )
				->where( 'passwd_recovery_code', $recovery_code )
				->get();

			// If above query indicates a match, change the password
			if( $query->num_rows() == 1 )
			{
				$user_data = $query->row();

				$this->db->where( 'user_id', $user_data->user_id )
					->update( 
						config_item('user_table'), 
						['passwd' => $this->authentication->hash_passwd( $password )] 
					);
			}
		}
	}

	// --------------------------------------------------------------
	public function change_password($password, $user_id)
	{
		$user_data['passwd'] = $this->authentication->hash_passwd( $password );
		$user_data['modified_at'] = time();
		
		$this->update_user_raw_data($user_id, $user_data);
	}
	
}