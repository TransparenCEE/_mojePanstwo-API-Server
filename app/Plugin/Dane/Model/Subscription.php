<?

class Subscription extends AppModel
{
    
    public $hasMany = array(
	    'SubscriptionChannel' => array(),
    );
    
    public function afterSave($created, $options) {
	    
	    $this->syncByData($this->data);
	    
    }
    
    public function add($data = array()) {
	    
	    $this->create();
        
        $sub = array(
	        'dataset' => $data['dataset'],
	        'object_id' => $data['object_id'],
	        'user_type' => $data['user_type'],
	        'user_id' => $data['user_id'],
		   	'cts' => date('Y-m-d h:i:j'),
        );
        	        
        if( $_sub = $this->find('first', array(
	        'fields' => array('id'),
	        'conditions' => array(
		        'user_type' => $sub['user_type'],
		        'user_id' => $sub['user_id'],
		        'dataset' => $sub['dataset'],
		        'object_id' => $sub['object_id'],
	        ),
        )) ) {
	    	
	    	$sub['id'] = $_sub['Subscription']['id'];
	        
	    }	        
        
        $channels = array();
    	foreach( $data['channel'] as $ch )
    		$channels[] = array(
        		'channel' => $ch,
    		);
        
		$data = array(
			'Subscription' => $sub,
			'SubscriptionChannel' => $channels,
		);
		
		
		if( isset($sub['id']) ) {
			$this->query("DELETE FROM `subscription_channels` WHERE `subscription_id`='" . addslashes( $sub['id'] ) . "'");
		}
				
		$return = $this->saveAssociated($data, array('deep' => true));
		return $return;
		
		
		/*
		$_serialize = array('message');
        	        
        
        
        if( isset( $this->request->data['q'] ) && $this->request->data['q'] )
        	$data['q'] = $this->request->data['q'];
        	
        if( isset( $this->request->data['channel'] ) && $this->request->data['channel'] )
        	$data['channel'] = $this->request->data['channel'];
        	
        if( isset( $this->request->data['conditions'] ) && $this->request->data['conditions'] )
        	$data['conditions'] = json_encode( $this->request->data['conditions'] );
        
        
        
        
        if( $sub = $this->Subscription->find('first', array(
	        'conditions' => array(
		        'user_type' => $data['user_type'],
		        'user_id' => $data['user_id'],
		        'hash' => $data['hash'],
	        ),
        )) ) {
	        	
	        	$url = $sub['Subscription']['url'];
	        	$this->set('url', $url);
	        	$_serialize[] = 'url';
	        	$message = 'Already Exists';
	        
        } else {       

        		        			        
	        if ($this->Subscription->save($data)) {
	        	
	        	$data['id'] = $this->Subscription->getInsertID();
	        	$add_data = $this->Subscription->generateData($data);		        	
	        	$data = array_merge($data, $add_data);
	        	$parent_id = $this->Subscription->index($data);
	        	
	        	$this->Subscription->save(array(
		        	'id' => $data['id'],
		        	'url' => $add_data['url'],
		        	'title' => $add_data['title'],
		        	'autotitle' => $add_data['title'],
		        	'parent_id' => $parent_id,
	        	));
	        	
	        	$this->set('url', $add_data['url']);
	        	$_serialize[] = 'url';
	        	
	            $message = 'Saved';
	        
	        } else {
	            $message = 'Error';
	        }
	        
	    }
	    */
	    
    }
    
    
    public function syncByData($data = array()) {
	    
	    if( empty($data) || !isset($data['Subscription']) || !isset($data['SubscriptionChannel']) )
	    	return false;
	    
	    $sub = $data['Subscription'];
	    $channels = array();
	    foreach( $data['SubscriptionChannel'] as &$ch ) {
	    	$ch['qs'] = array();
	    	$ch['channel'] = (int) $ch['channel'];
	    	$channels[] = (int) $ch['channel'];
	    }
	    
	    $channels = array_unique($channels);
	    		    
	    $ES = ConnectionManager::getDataSource('MPSearch');	    
	    
	    $parent_doc = $ES->API->search(array(
		    'index' => 'mojepanstwo_v1',
		    'type' => 'objects',
		    'body' => array(
			    'query' => array(
				    'bool' => array(
					    'must' => array(
						    array(
							    'term' => array(
								    'dataset' => $sub['dataset'],
							    ),
						    ),
						    array(
							    'term' => array(
								    'id' => $sub['object_id'],
							    ),
						    ),
					    ),
				    ),
			    ),
		    ),
	    ));
	    
	    if( 
	    	( $parent_doc['hits']['total'] === 1 ) && 
	    	( $_id = $parent_doc['hits']['hits'][0]['_id'] )
	    ) {
		    		    	    
		    $params = array();
			$params['index'] = 'mojepanstwo_v1';
			$params['type']  = '.percolator';
			$params['id']    = $sub['id'];
			$params['parent'] = $_id;
			
			$cts = strtotime( $sub['cts'] );
			$mask = "Ymd\THis\Z";
			
			
			/*
			if(
				isset( $data['conditions'] ) && 
				$data['conditions'] && 
				( $data['conditions'] = json_decode($data['conditions'], true) )
			) {
				$es_conditions = $data['conditions'];
			} else {
				$es_conditions = array();
			}
			*/
			
			$es_conditions = array();
			$es_conditions['_feed'] = array (
				'dataset' => $sub['dataset'],
				'object_id' => $sub['object_id'],
			);
			
			/*
			if( isset($data['q']) && $data['q'] )
				$es_conditions['q'] = $data['q'];
				
			if( isset($data['channel']) && $data['channel'] )
				$es_conditions['_feed']['channel'] = $data['channel'];
			*/
						
			if( !empty($channels) )
				$es_conditions['_feed']['channel'] = $channels;
			
			$es_query = $ES->buildESQuery(array(
				'conditions' => $es_conditions,
			));
						
			$params['body']  = array(
				'id' => $sub['id'],
				'query' => $es_query['body']['query']['function_score']['query'],
				'cts' => date($mask, $cts),
				'user_type' => $sub['user_type'],
				'user_id' => $sub['user_id'],
				'channels' => $data['SubscriptionChannel'],
			);
			
			/*
			if( isset($data['q']) && $data['q'] )
				$params['body']['q'] = $data['q'];
				
			if( isset($data['channel']) && $data['channel'] )
				$params['body']['channel'] = $data['channel'];
			*/
			
			$ret = $ES->API->index($params);	
			// debug( $ret );
			return $_id;	    
		    
		}
	    
    }
    
    public function afterDelete() {
		
		if( $this->data['id'] ) {
			
			$ES = ConnectionManager::getDataSource('MPSearch');
			$deleteParams = array();
			$deleteParams['index'] = 'mojepanstwo_v1';
			$deleteParams['type'] = '.percolator';
			$deleteParams['id'] = $this->data['id'];
			$ret = $ES->API->delete($deleteParams);
						
		}
	
	}
    
    public function generateData($data = array()) {
	    
	    $base = '/dane';
	    
	    if( $data['dataset']=='rady_gmin' ) {
		    
		    $base .= '/gminy/903,krakow/rada';
		    
	    } elseif( $data['dataset']=='urzedy_gmin' ) {

		    $base .= '/gminy/903,krakow/urzad';
	    
	    } else {
	    
		    $base .= '/' . $data['dataset'];
		    $base .= '/' . $data['object_id'];
			
			if( $data['dataset']=='prawo' )
				$base .= '/feed';
			
		}
		
		$title_parts = array();
		
	    $query = array('subscription' => $data['id']);
	    
	    if( isset($data['q']) && $data['q'] ) {
	    	$query['q'] = $data['q'];
	    	$title_parts[] = '"' . $query['q'] . '"';
	    }
	    	
	    if( 
	    	isset($data['channel']) && 
	    	$data['channel'] && 
	    	( $query['channel'] = $data['channel'] )
	    ) {
	    	
	    	App::import('model','Dane.DatasetChannel');
			$DatasetChannel = new DatasetChannel();
			if( $channel = $DatasetChannel->find('first', array(
				'fields' => array(
					'title'
				),
				'conditions' => array(
					'creator_dataset' => $data['dataset'],
					'channel' => $data['channel'],
				),
			)) ) {
				
				$title_parts[] = $channel['DatasetChannel']['title'];
				
			}
			
	    }
	    	
	    if( 
	    	isset($data['conditions']) && 
	    	is_string($data['conditions']) && 
	    	( $query['conditions'] = json_decode($data['conditions'], true) ) 
	    ) {
	    	$title_parts[] = 'Dodatkowe filtry';
	    }
	    	    
	    return array(
	    	'url' => $base . '?' . http_build_query($query),
	    	'title' => empty($title_parts) ? 'Wszystkie dane' : implode(' - ', $title_parts),
	    );
	    
    }
    
    public function transfer_anonymous($anonymous_user_id, $user_id) {
		
		if(
			( $db = ConnectionManager::getDataSource('default') ) && 
			( $where = "user_type='anonymous' AND user_id='" . addslashes( $anonymous_user_id ) . "'" ) && 
			( $subs = $db->query("SELECT id, parent_id FROM subscriptions WHERE $where") ) 
		) {
			
			$ES = ConnectionManager::getDataSource('MPSearch');
						
			foreach( $subs as $sub ) {
			    $ES->API->update(array(
				    'index' => 'mojepanstwo_v1',
				    'type' => '.percolator',
				    'id' => $sub['subscriptions']['id'],
				    'parent' => $sub['subscriptions']['parent_id'],
				    'body' => array(
					    'doc' => array(
						    'user_type' => 'account',
					    	'user_id' => $user_id,
					    ),
				    ),
			    ));
			}
			
			$db->query("UPDATE subscriptions SET `user_type`='account', `user_id`='" . addslashes( $user_id ) . "' WHERE $where");
			
			return true;
			
		} else return false;
		
	}

}


