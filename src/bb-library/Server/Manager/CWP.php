<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * CWP API
 * @see https://docs.control-webpanel.com/docs/developer-tools/api-manager
 */
class Server_Manager_CWP extends Server_Manager
{
	public function init()
    {
        if (!extension_loaded('curl')) {
            throw new Server_Exception('cURL extension is not enabled');
        }

        if(empty($this->_config['host'])) {
            throw new Server_Exception('Server manager "CWP" is not configured properly. Hostname is not set!');
        }

        if(empty($this->_config['accesshash'])) {
			throw new Server_Exception('Server manager "CWP" is not configured properly. API Key / Access Hash is not set!');
        } else {
            $this->_config['accesshash'] = preg_replace("'(\r|\n)'","",$this->_config['accesshash']);
		}

		if(!$this->_config['secure']){
			/*
			 * Is this true? Docs specify the API port is for SSL and list no other alternative.
			 * Regardless, I have no plan to test it as anyone is an idiot if they plan to provide hosting and not use SSL to secure things.
			 */
			throw new Server_Exception('Server manager "CWP" is not configured properly. CWP API will only accept a secure connection!');
		}
		/* 
		 * Default API port. Not sure if it can be overridden, but I've opted to leave the option anyways.
		 */
		if(empty($this->_config['port'])){
			$this->_config['port'] = '2304';
		}
	}

    public static function getForm()
    {
        return array(
            'label'     =>  'CWP',
        );
    }

    public function getLoginUrl()
    {
        $host = $this->_config['host'];
        return 'https://'.$host.':2083';
    }

    public function getResellerLoginUrl()
    {
        $host = $this->_config['host'];
        return 'https://'.$host.':2031';
    }

    /*
	 * CWP Doesn't have a function to test the connection, so we simply ask it to list the server type and check the response
	 */
    public function testConnection()
    {
		$APIKey = $this->_config['accesshash'];	
		
		$host = $this->_config['host'];
		$port = $this->_config['port'];

        $data = array(
            'key'     => $APIKey,
            'action'  => 'list',
        );
		
		return makeAPIRequest($host, $port, 'typeserver', $data);
    }

    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info('Synchronizing account with server '.$a->getUsername());
		
		$APIKey = $this->_config['accesshash'];	
		
		$host = $this->_config['host'];
		$port = $this->_config['port'];

        $data = array(
            'key'     => $APIKey,
            'action'  => 'list',
            'user'    => $a->getUsername()
        );
		
		$new = clone $a;
		$acc = makeAPIRequest($host, $port, 'accountdetail', $data);

		if($acc['account_info']['state'] == 'suspended'){
		    $new->setSuspended(true);
		} else {
		   	$new->setSuspended(false);
		}
		
        $new->setDomain($acc['domains']['0']['domain']);

        return $new;
    }

	public function createAccount(Server_Account $a)
    {
		$this->getLog()->info('Creating account '.$a->getUsername());
        
		$client = $a->getClient();
        $package = $a->getPackage()->getName();

		$APIKey = $this->_config['accesshash'];	

		$host = $this->_config['host'];
		$port = $this->_config['port'];
        $ip = $this->_config['ip'];

		$data = array(
		    'key'          => $APIKey,
			'action'       => 'add', 
			'domain'       => $a->getDomain(), 
			'user'         => $a->getUsername(), 
			'pass'         => $a->getPassword(), 
			'email'        => $client->getEmail(), 
			'package'      => $package,
			'server_ips'   => $ip,
			'encodepass'   => false
		);
		if($a->getReseller()) {
            $data['reseller'] = 1;
        }
        return makeAPIRequest($host, $port, 'account', $data);
	}

	public function suspendAccount(Server_Account $a)
    {
		$this->getLog()->info('Suspending account '.$a->getUsername());

		$client = $a->getClient();
		
		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
		    'key'      => $APIKey,
			'action'   => 'susp',
			'user'     => $a->getUsername()
		);
        return makeAPIRequest($host, $port, 'account', $data);
	}

	public function unsuspendAccount(Server_Account $a)
    {
		$this->getLog()->info('Un-suspending account '.$a->getUsername());

		$client = $a->getClient();
		
		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
		    'key'      => $APIKey,
			'action'   => 'unsp',
			'user'     => $a->getUsername()
		);
        return makeAPIRequest($host, $port, 'account', $data);
    }

	public function cancelAccount(Server_Account $a)
    {
		$this->getLog()->info('Canceling account '.$a->getUsername());

		$client = $a->getClient();
		
		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
		    'key'     => $APIKey,
			'action'  => 'del',
			'user'    => $a->getUsername(),
			'email'   => $client->getEmail()
		);
        return makeAPIRequest($host, $port, 'account', $data);
	}
    
	/*
	 * For unknown reasons, this doesn't work.
	 */
	public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
		$this->getLog()->info('Changing package on account '.$a->getUsername());

        $package = $a->getPackage()->getName();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
		    'key'      => $APIKey,
			'action'   => 'upd',
			'user'     => $a->getUsername(),
			'package'  => $package
		);
        return makeAPIRequest($host, $port, 'changepack', $data);
	}

    public function changeAccountPassword(Server_Account $a, $new)
    {
		$this->getLog()->info('Changing password on account '.$a->getUsername());

		$client = $a->getClient();

		$APIKey = $this->_config['accesshash'];
		
		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
		    'key'     => $APIKey,
			'action'  => 'udp',
			'user'    => $a->getUsername(),
			'pass'    => $new
		);
        return makeAPIRequest($host, $port, 'changepass', $data);
    }

    /*
	 * Function graveyard for things CWP doesn't support
	 */
    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception('CWP Does not support username changes through the API');
    }

    public function changeAccountDomain(Server_Account $a, $new)
    {
        throw new Server_Exception('CWP Does not support changing the primary domain name');
    }

    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception('CWP Does not support changing the IP');
    }
}
	/**
	 * Makes the CURL request to the server
	 */
    function makeAPIRequest($host, $port, $func, $data)
	{
        $url = 'https://'.$host.":".$port.'/v1/'.$func;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt ($ch, CURLOPT_POST, 1);
		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);
        
		if(!empty($response['status'])){
			$status = $response['status'];
		} else {
		    $status = 'Error';
		}
		if(!empty($response['result'])){
			$result = $response['result'];
		} else {
		    $result = null;	
		}

		if($status == 'OK' && $func != 'accountdetail'){
			return true;
			error_log('OK',0);
		} else {
			if($status == 'Error'){
				error_log('Error',0);
		        return 0;
			} else {
			    error_log('Results',0);
			    return $result;
			}
		}
	}
