<?

class DocumentsController extends AppController
{
	
    public $uses = array('Dane.Dataobject', 'Pisma.Document');
    public $components = array('Session', 'RequestHandler');
		
	private function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range < 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
	}
	
	public function generateID($length = 5)
	{
		
	    $id = "";
	    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
	    for($i=0;$i<$length;$i++)
	        $id .= $codeAlphabet[$this->crypto_rand_secure(0,strlen($codeAlphabet))];
	    return $id;
			
	}

    public function search() {
               
        $this->Auth->deny();
                
        $params = array(
	        'page' => ( 
	        	isset($this->request->query['page']) && 
	        	is_numeric($this->request->query['page'])
	        ) ? $this->request->query['page'] : 1,
	        'q' => (isset( $this->request->query['q'] ) && $this->request->query['q']) ? $this->request->query['q'] : false,
	        'user_type' => $this->Auth->user('type'),
	        'user_id' => $this->Auth->user('id'),
        );
        
        if( 
        	isset($this->request->query['conditions']) && 
        	( $conditions = $this->request->query['conditions'] )
        ) {
        	        	
	        if( isset($conditions['access']) && in_array($conditions['access'], array('private', 'public')) )
	        	$params['conditions']['access'] = $conditions['access'];
	        	
	        if( isset($conditions['template']) )
	        	$params['conditions']['template_id'] = $conditions['template'];
	        	
	        if( isset($conditions['sent']) )
	        	$params['conditions']['sent'] = (boolean) $conditions['sent'];
	        	
	        if( isset($conditions['to']) && ($parts = explode(':', $conditions['to'])) && ( count($parts) >= 2 ) ) {
		        		        
	        	$params['conditions']['to_dataset'] = $parts[0];
	        	$params['conditions']['to_id'] = $parts[1];

	        }

        }
						
		$search = $this->Document->search($params);
        $this->setSerialized('search', $search);
        
    }

    public function view() {
                
        $temp = $this->readOrThrow($this->request->params['id']);
    	$this->setSerialized('object', $temp);
        
    }
	
	public function update($id) {

		$status = false;		

		if( isset($this->data['access']) ) {
			
			$status = $this->Document->changeAccess($id, array(
				'access' => $this->data['access'],
				'user_type' => $this->Auth->user('type'),
		        'user_id' => $this->Auth->user('id'),
			));
			
		} elseif(
			isset( $this->data['name'] ) && 
			( $name = $this->data['name'] )
		) {
			
			$status = $this->Document->rename($id, array(
				'name' => $name,
				'user_type' => $this->Auth->user('type'),
		        'user_id' => $this->Auth->user('id'),
			));
			
		}
		
		$this->set('status', $status);
		$this->set('_serialize', 'status');	
		
	}
	
    public function save($id = null) {
                
        $this->Auth->deny();
        
        $map = array(
        	'id' => 'id', 
        	'data_pisma' => 'date',
        	'nazwa' => 'name',
        	'tytul' => 'title',
        	'tresc' => 'content',
        	'tresc_html' => 'content_html',
        	'adresat' => 'to_str',
        	'nadawca' => 'from_str',
        	'miejscowosc' => 'from_location',
        	'data' => 'date',
        	'szablon_id' => 'template_id',
        	'podpis' => 'from_signature',
        );
                
                
        $data = $this->request->data;
        if (empty($data)) {
            $data = array();
        }
                
        
        $adresat_id = isset($data['adresat_id']) ? $data['adresat_id'] : false;
        
        $temp = array();
        foreach( $data as $k => $v )
        	if( array_key_exists($k, $map) )
        		$temp[ $map[$k] ] = $v;
                
        $temp['from_user_type'] = $this->Auth->user('type');
        $temp['from_user_id'] = $this->Auth->user('id');
                
        
        $data = $temp;
        unset( $temp );
        
        if(
	        $adresat_id && 
	        ( $parts = explode(':', $adresat_id) ) &&
	        ( count($parts)>1 )
        )
        	$data = array_merge($data, array(
	        	'to_dataset' => $parts[0],
	        	'to_id' => $parts[1],
        	));
        
        
                
        
        App::import('model','DB');
		$DB = new DB();
        
        
        // edit & create in one func, path param has precedence
        if ($id != null) {
            $data['alphaid'] = $id;
        }
				
        if (isset($data['alphaid']) && $data['alphaid']) {
            
            $data['modified_at'] = date('Y-m-d H:i:s');
            $data['saved'] = '1';
            $data['saved_at'] = date('Y-m-d H:i:s');

        } else {
	        
	        
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['hash'] = substr(bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM)), 0, 64);
            $data['alphaid'] = $this->generateID(5);
	        $data['name'] = false;
	        $data['saved'] = '0';
	       
	    }
	    
	    
        if(
	        isset( $data['template_id'] ) && 
        	$data['template_id'] && 
        	$template = $DB->selectAssoc("SELECT nazwa, tresc, tytul FROM pisma_szablony WHERE id='" . addslashes( $data['template_id'] ) . "'")
        ) {
	        
        	$data['title'] = $template['tytul'] ? $template['tytul'] : $template['nazwa'];
        	
        	if( $data['saved']=='0' ) {
	        	
	        	$data['content'] = $template['tresc'];
	        	
	        	if( !$data['name'] && $template['nazwa'] )
	        		$data['name'] = $template['nazwa'];
        		
        	}
        	
        }
        
        
        if(
	        isset( $data['to_dataset'] ) && 
        	$data['to_dataset'] && 
        	isset( $data['to_id'] ) && 
        	$data['to_id']
        ) {
	       	
	       	if( $data['to_dataset']=='pisma_adresaci' ) {
		       	$pisma_adresaci = $this->DB->selectAssoc("SELECT dataset, object_id FROM pisma_adresaci WHERE id='" . addslashes( $data['to_id'] ) . "' LIMIT 1");
		       	$data['to_dataset'] = $pisma_adresaci['dataset'];
		       	$data['to_id'] = $pisma_adresaci['object_id'];
	       	}
	       	
	       	if(
		       	( $data['to_dataset']=='instytucje' ) && 
	        	( $to = $DB->selectAssoc("SELECT id, nazwa, email, adres_str FROM instytucje WHERE id='" . addslashes( $data['to_id'] ) . "'" ) ) 
	        ) {
	       		       	 
	        	$data['to_str'] = '<p>' . $to['nazwa'] . '</p><p>' . $to['adres_str'] . '</p>';
	        	$data['to_name'] = $to['nazwa'];
	        	$data['to_email'] = $to['email'];
        	
        	} elseif(
	        	( $data['to_dataset']=='radni_gmin' ) && 
	        	( $to = $DB->selectAssoc("SELECT pl_gminy_radni.id, pl_gminy_radni.nazwa, pl_gminy_radni_krakow.email FROM pl_gminy_radni LEFT JOIN pl_gminy_radni_krakow ON pl_gminy_radni.id=pl_gminy_radni_krakow.id WHERE pl_gminy_radni.id='" . addslashes( $data['to_id'] ) . "'" ) ) 
        	) {
	        	
	        	$data['to_str'] = '<p>Radny Miasta Kraków</p><p>' . $to['nazwa'] . '</p><p>' . $to['email'] . '</p>';
	        	$data['to_name'] = 'Radny Miasta Kraków - ' . $to['nazwa'];
	        	$data['to_email'] = $to['email'];
	        	
        	} elseif(
	        	( $data['to_dataset']=='poslowie' ) && 
	        	( $to = $DB->selectAssoc("SELECT s_poslowie_kadencje.id, s_poslowie_kadencje.nazwa, s_poslowie_kadencje.email, s_poslowie_kadencje.pkw_plec FROM s_poslowie_kadencje LEFT JOIN s_kluby ON s_poslowie_kadencje.klub_id=s_kluby.id WHERE s_poslowie_kadencje.id='" . addslashes( $data['to_id'] ) . "'" ) ) 
        	) {
	        	        		
	        	
	        	if( $to['pkw_plec']=='K' ) {
	        	
		        	$data['to_str'] = '<p>Posłanka na Sejm RP</p><p>' . $to['nazwa'] . '</p><p>' . $to['email'] . '</p>';
		        	$data['to_name'] = 'Posłanka - ' . $to['nazwa'];
		        	$data['content'] = str_replace(array(
		        		'{$szanowny_panie_posle}',
		        		'{$pan_posel}',
		        	), array(
		        		'Szanowna Pani Posłanko',
		        		'Pani Posłanka'
		        	), $data['content']);
	        	
	        	} else {
		        	
		        	$data['to_str'] = '<p>Poseł na Sejm RP</p><p>' . $to['nazwa'] . '</p><p>' . $to['email'] . '</p>';
		        	$data['to_name'] = 'Poseł - ' . $to['nazwa'];
		        	$data['content'] = str_replace(array(
		        		'{$szanowny_panie_posle}',
		        		'{$pan_posel}',
		        	), array(
		        		'Szanowny Panie Pośle',
		        		'Pan Poseł'
		        	), $data['content']);
		        	
	        	}
	        	
	        	$data['to_email'] = $to['email'];

            } elseif(
                ( $data['to_dataset']=='gminy' ) &&
                ( $to = $DB->selectAssoc("SELECT pl_gminy.id, pl_gminy.nazwa, pl_gminy.email, pl_gminy.szef_stanowisko, pl_gminy.adres FROM pl_gminy WHERE pl_gminy.id='" . addslashes( $data['to_id'] ) . "'" ) )
            ) {

                $data['to_str'] = '<p>' . $to['szef_stanowisko'] . ' ';
                switch ($to['szef_stanowisko']) {
                    case 'Wójt': {
                        $data['to_str'].='Gminy';
                        break;
                    }
                    case 'Burmistrz': {
                        $data['to_str'].='Miasta';
                        break;
                    }
                    case 'Prezydent': {
                        $data['to_str'].='Miasta';
                        break;
                    }
                }

                    $data['to_str'].= ' ' . $to['nazwa'] . '</p><p>' . $to['adres'] . '</p><p>' . $to['email'] . '</p>';
                $data['to_name'] = $to['szef_stanowisko'] . ' ' . $to['nazwa'];
                $data['to_email'] = $to['email'];

            } elseif(
                ( $data['to_dataset']=='rada_gminy' ) &&
                ( $to = $DB->selectAssoc("SELECT pl_gminy.id, pl_gminy.nazwa, pl_gminy.email, pl_gminy.rada_nazwa, pl_gminy.adres FROM pl_gminy WHERE pl_gminy.id='". addslashes( $data['to_id'] ) ."'" ) )
            ) {
                $data['to_str'] = '<p>' . $to['rada_nazwa'] . '</p><p>' . $to['adres'] . '</p><p>' . $to['email'] . '</p>';
                $data['to_name'] = $to['rada_nazwa'];
                $data['to_email'] = $to['email'];

            } elseif(
	        	( $data['to_dataset']=='zamowienia_publiczne_zamawiajacy' ) && 
	        	( $to = $DB->selectAssoc("SELECT uzp_zamawiajacy.id, uzp_zamawiajacy.nazwa, uzp_zamawiajacy.email, uzp_zamawiajacy.ulica, uzp_zamawiajacy.nr_domu, uzp_zamawiajacy.nr_miesz, uzp_zamawiajacy.miejscowosc, uzp_zamawiajacy.kod_poczt FROM uzp_zamawiajacy WHERE uzp_zamawiajacy.id='" . addslashes( $data['to_id'] ) . "'" ) ) 
        	) {
	        	
	        	$data['to_str'] = '<p>' . $to['nazwa'] . '</p><p>' . $to['ulica'] . ' ' . $to['nr_domu'] . ' ' . $to['nr_miesz'] . '</p><p>' . $to['kod_poczt'] . ' ' . $to['miejscowosc'] . '</p><p>' . $to['email'] . '</p>';
	        	$data['to_name'] = $to['nazwa'];
	        	$data['to_email'] = $to['email'];
	        	
        	} elseif(
	        	( $data['to_dataset']=='krs_podmioty' ) && 
	        	( $to = $DB->selectAssoc("SELECT id, nazwa_pelna, adres_ulica, adres_numer, adres_lokal, adres_miejscowosc, adres_kod_pocztowy, adres_poczta, adres_kraj, email FROM krs_pozycje WHERE id='" . addslashes( $data['to_id'] ) . "'" ) ) 
        	) {
	        	
	        	$data['to_str'] = '<p>' . $to['nazwa_pelna'] . '</p><p>' . $to['adres_ulica'] . ' ' . $to['adres_numer'];
	        	
	        	if( $to['adres_lokal'] )
		        	$data['to_str'] .= ' ' . $to['adres_lokal'];
	        	
	        	$data['to_str'] .= '</p><p>' . $to['adres_kod_pocztowy'] . ' ' . $to['adres_poczta'] . '</p>';
	        	
	        	if( $to['email'] )
		        	$data['to_str'] .= '<p>' . $to['email'] . '</p>';
	        	
	        	$data['to_name'] = $to['nazwa_pelna'];
	        	$data['to_email'] = $to['email'];
	        	
        	}
        	
        }
	                
        	        	        	        

		if( !isset($data['to_dataset']) )
	        $data['to_dataset'] = false;	        
        
		if( !isset($data['to_id']) )        
			$data['to_id'] = false;
			
		if( 
			( $data['saved']=='0' ) && 
			!$data['name']
		)
        	$data['name'] = 'Nowe pismo';
        			
		$data['slug'] = @substr(Inflector::slug($data['name'], '-'), 0, 127);
                
        // TODO powinno być zwrócone w innym formacie dTt, czemu cake sam tego nie formatuje w bazie?! Albo zwracajac?
        $data['modified_at'] = date('Y-m-d H:i:s');
				
	    
	    /*
	    debug( $data );
	    $this->Document->save(array('Document' => $data));
	    $dbo = $this->Document->getDatasource();
		$logs = $dbo->getLog();
		$lastLog = end($logs['log']);
		echo $lastLog['query']; die();   
		*/
	       
	    
	    if( $data['saved']=='0' )	        	
	        $this->Document->create();  
	    
	    
	    $data['from_user_name'] = '';
	    if( $data['from_user_type']=='account' ) {
		    
		    $this->loadModel('Paszport.User');
		    $user = $this->User->find('first', array(
			    'conditions' => array(
				    'User.id' => $data['from_user_id'],
			    ),
		    ));
		    
		    $data['from_user_name'] = $user['User']['username'];
		    
	    }
	    
        if ($doc = $this->Document->save(array('Document' => $data))) {
            $this->response->statusCode(201);  // 201 Created
            
            $url = '/pisma/' . $doc['Document']['alphaid'];
                        
            if( $doc['Document']['slug'] )
            	$url .= ',' . $doc['Document']['slug'];
            
            $this->setSerialized('object', array(
	            'id' => $doc['Document']['alphaid'],
	            'url' =>  $url,
            ));

        } else {
            // TODO format returned validation errors
            throw new ValidationException($this->Document->validationErrors);
        }
    }

    public function send($id = null) {
                
        if( $id && ($this->Auth->user('type')=='account') ) {
	        	        
	        $status = $this->Document->send(array(
		        'id' => $id,
		        'user_id' => $this->Auth->user('id'),
		        'user_type' => $this->Auth->user('type'),
	        ));
	        $this->setSerialized('status', $status);
	    
	    } elseif( isset($this->request->data['email']) ) {
	    
	    	$status = $this->Document->send(array(
		        'id' => $id,
		        'user_id' => $this->Auth->user('id'),
		        'user_type' => $this->Auth->user('type'),
		        'email' => $this->request->data['email'],
		        'name' => @$this->request->data['name'],
	        ));
	        $this->setSerialized('status', $status);
	    	
        } else throw new BadRequestException();
        
    }

    public function delete() {
        
        $this->Auth->deny();
        
        $params = array(
	        'from_user_type' => $this->Auth->user('type'),
	        'from_user_id' => $this->Auth->user('id'),
        );
                
        $status = $this->Document->delete($this->request->data['id'], $params);
        $this->setSerialized('status', $status);
        
    }
	
	public function transfer_anonymous() {
		
		$status = false;
				
		if(
			( $user = $this->Auth->user() ) && 
			isset($this->request->query['anonymous_user_id']) && 
			$this->request->query['anonymous_user_id']
		) {
			
			$status = $this->Document->transfer_anonymous($this->request->query['anonymous_user_id'], $this->Auth->user('id'));
			
		}
		
		$this->setSerialized('status', $status);
		
	}
	
    private function readOrThrow($id) {
	    	    
        $object = $this->Document->find('first', array(
	        'conditions' => array(
		        'deleted' => '0',
		        'id' => $id,
		        'OR' => array(
			        array(
			        	'from_user_type' => $this->Auth->user('type'),
				        'from_user_id' => $this->Auth->user('id'),
				    ),
				    'access' => 'public',
			    )
	        ),
        ));
        
        /*
        $dbo = $this->Document->getDatasource();
		$logs = $dbo->getLog();
		$lastLog = end($logs['log']);
		echo $lastLog['query']; die();
		*/
        
        if (!isset($object['Document']) || empty($object['Document'])) {
            throw new NotFoundException();
        }
        /*
        if ($object['Document']['from_user_id'] != $this->user_id) {
            throw new ForbiddenException();
        }
        */

        return $object['Document'];
    }
}