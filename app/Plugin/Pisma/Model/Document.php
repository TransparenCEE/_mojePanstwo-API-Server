<?php
App::uses('AppModel', 'Model');
/**
* PismaDocument Model
*
 * @property Paszport.User $User
 * @property Template $Template
*/
class Document extends AppModel {

    public $useTable = 'pisma_documents';
    public $primaryKey = 'alphaid';
    public $recursive = -1;
	
	public $virtualFields = array(
		'data_pisma' => 'Document.date',
	    'tytul' => 'Document.title',
	    'nazwa' => 'Document.name',
	    'tresc' => 'Document.content',
    	'nadawca' => 'Document.from_str',
    	'adresat' => 'Document.to_str',
    	'miejscowosc' => 'Document.from_location',
    	'data' => 'Document.date',
    	'adresat_id' => 'Document.to_id',
    	'szablon_id' => 'Document.template_id',
    	'podpis' => 'Document.from_signature',
    	'id' => 'Document.alphaid',
	);
	
	
        	
	
    /**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		/*
		'alphaid' => array(
			'maxLength' => array(
				'rule' => array('maxLength', 5),
			),
            'notEmpty' => array(
                'rule' => 'notEmpty',
                'required' => true,
            ),
		),
		*/
		/*
		'hash' => array(
			'maxLength' => array(
				'rule' => array('maxLength', 64),
			),
            'notEmpty' => array(
                'rule' => 'notEmpty',
                'required' => true,

                //'message' => 'Your custom message here',
                //'last' => false, // Stop validation after this rule
                //'on' => 'create', // Limit validation to 'create' or 'update' operations
            ),
		),
		'access' => array(
			'inList' => array(
				'rule' => array('inList', array('private', 'public')),
                'required' => true,
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		*/
		/*
		'from_user_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
                'required' => false,
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		*/
		'from_name' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
                'required' => false,
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
            'maxLength' => array(
                'rule' => array('maxLength', 255),
            ),
		),
		'from_email' => array(
            'email' => array(
                'rule' => 'email',
                'required' => false,
            ),
            'maxLength' => array(
                'rule' => array('maxLength', 255),
            ),
		),
        'subject' => array(
            'maxLength' => array(
                'rule' => array('maxLength', 255),
            ),
        ),
        /*
		'to_dataset' => array(
			'notEmpty' => array(
				'rule' => array('notEmpty'),
                'required' => false,
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		*/
		/*
		'to_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
                'required' => false,
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			)
		),
		*/
		'sent' => array(
			'boolean' => array(
				'rule' => array('boolean'),
                'required' => false,
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);

        //The Associations below have been created with all possible keys, those that are not needed can be removed
        
/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'User' => array(
			'className' => 'Paszport.User',
			'foreignKey' => 'from_user_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'Template' => array(
			'className' => 'Pisma.Template',
			'foreignKey' => 'template_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
	
	
	
	public function afterSave($created, $options) {
				
		if( 
			( $data = $this->data['Document'] ) && 
			isset( $data['alphaid'] ) && 
			$data['alphaid'] && 
			( $saved = (isset($data['saved']) ? (boolean) $data['saved'] : false) )
		) {
						
			$this->sync( $data['alphaid'] );		
						
		}
		
	}
	
	private function prepareAggs($params, $field = false) {
		
		if( $field && isset($params['and']) && isset($params['and']['filters']) ) {
			
			$filters = array();
			
			foreach( $params['and']['filters'] as $f ) {
				
				$keys = array_keys( $f['term'] );
				if( $keys[0] !== $field )
					$filters[] = $f;
				
			}
			
			$params['and']['filters'] = $filters;
			return $params;
			
		} else return $params;
		
	}
	
	public function search($params) {
		
		$ES = ConnectionManager::getDataSource('MPSearch');
			
		$page = isset($params['page']) ? $params['page'] : 1;
		$from = ($page-1) * 20;		
		
		$filtered = array(
	        'filter' => array(
	            'and' => array(
	                'filters' => array(
	                    array(
	                        'term' => array(
	                        	'from_user_type' => $params['user_type'],
	                        ),
	                    ),
	                    array(
	                        'term' => array(
	                        	'from_user_id' => $params['user_id'],
	                        ),
	                    ),
	                    array(
	                        'term' => array(
	                        	'deleted' => false,
	                        ),
	                    ),
	                ),
	                '_cache' => true,
	            ),
	        ),
	    );
	    	    
	    if( isset($params['conditions']) ) {
		    foreach( $params['conditions'] as $key => $val ) {
			    
			    $filtered['filter']['and']['filters'][] = array(
				    'term' => array(
					    $key => $val,
				    ),
			    );
			    
		    }
	    }
	    	      
	    if( $params['q'] )
	    	$filtered['query'] = array(
		        'match' => array(
			        'text' => $params['q'],
		        ),
	        );
		
		$es_params = array(
			'index' => 'mojepanstwo_v1',
			'type' => 'letters',
			'body' => array(
				'from' => $from, 
				'size' => 20,
				'query' => array(
					'filtered' => $filtered,
				),
				'partial_fields' => array(
					'data' => array(
						'include' => array('id', 'alphaid', 'name', 'slug', 'date', 'created_at', 'modified_at', 'to_label', 'hash', 'sent', 'sent_at'),
					),
				),
				'sort' => array(
					'modified_at' => 'desc',
				),
				'aggs' => array(
					'all' => array(
						'global' => new \stdClass(),
						'aggs' => array(
							'access' => array(
								'filter' => $this->prepareAggs( $filtered['filter'], 'access' ),
								'aggs' => array(
									'filtered' => array(
										'terms' => array(
											'field' => 'access',
										),
									),
								),
							),
							'sent' => array(
								'filter' => $this->prepareAggs( $filtered['filter'], 'sent' ),
								'aggs' => array(
									'filtered' => array(
										'terms' => array(
											'field' => 'sent',
										),
									),
								),
							),
							'to_dataset' => array(
								'filter' => $this->prepareAggs( $filtered['filter'], 'to_dataset' ),
								'aggs' => array(
									'filtered' => array(
							            'terms' => array(
								            'field' => 'to_dataset',
								            'exclude' => array(
									            'pattern' => '(|false)'
								            ),
							            ),
							            'aggs' => array(
								            'to_id' => array(
									            'terms' => array(
										            'field' => 'to_id',
										            'exclude' => array(
											            'pattern' => '(|false)'
										            ),
									            ),
									            'aggs' => array(
										            'to_name' => array(
											            'terms' => array(
												            'field' => 'to_label',
											            ),
										            ),
									            ),
								            ),
							            ),
							        ),
							    ),
					        ),
					        'template_id' => array(
								'filter' => $this->prepareAggs( $filtered['filter'], 'template_id' ),
								'aggs' => array(
									'filtered' => array(
								        'terms' => array(
									        'field' => 'template_id',
									        'exclude' => array(
									            'pattern' => '(|false)'
								            ),
								        ),
								        'aggs' => array(
									        'template_label' => array(
										        'terms' => array(
											        'field' => 'template_label',
										        ),
										    ),
								        ),
								    ),
								),
					        ),
					    ),
				    ),
				),
			),
		);
		
		// debug( $es_params );
		$data = $ES->API->search($es_params);
		// debug( $data ); die();
				
		$items = array();
				
		foreach( $data['hits']['hits'] as $hit ) {			
			$hit['fields']['data'][0]['to_name'] = $hit['fields']['data'][0]['to_label'];
			unset( $hit['fields']['data'][0]['to_label'] );
			$items[] = $hit['fields']['data'][0];
		}
		
		return array(
			'performance' => array(
				'took' => $data['took'],
			),
			'pagination' => array(
				'page' => $page,
				'perPage' => 20,
				'total' => $data['hits']['total'],
			),
			'items' => $items,
			'aggs' => $data['aggregations'],
		);
		
	}
	
	public function rename($id, $params) {
		
		if( !$params['name'] )
			return false;
		
		App::import('model','DB');
		$DB = new DB();
		
		$q = "UPDATE `pisma_documents` SET `name`='" . addslashes( $params['name'] ) . "' WHERE `alphaid`='" . addslashes($id) . "' AND `from_user_type`='" . addslashes( $params['user_type'] ) . "' AND `from_user_id`='" . addslashes( $params['user_id'] ) . "' LIMIT 1";
		$DB->query($q);
		
		if( $DB->getAffectedRows() ) {
			
			$ES = ConnectionManager::getDataSource('MPSearch');
			$ES->API->update(array(
			    'index' => 'mojepanstwo_v1',
			    'type' => 'letters',
			    'id' => $id,
			    'body' => array(
				    'doc' => array(
					    'name' => $params['name'],
				    ),
			    ),
		    ));
		    
		    return true;
			
		} else return true;
		
	}
	
	public function changeAccess($id, $params) {
		
		if( !$params['access'] )
			return false;
		
		App::import('model','DB');
		$DB = new DB();
		
		$q = "UPDATE `pisma_documents` SET `access`='" . addslashes( $params['access'] ) . "' WHERE `alphaid`='" . addslashes($id) . "' AND `from_user_type`='" . addslashes( $params['user_type'] ) . "' AND `from_user_id`='" . addslashes( $params['user_id'] ) . "' LIMIT 1";
		$DB->query($q);
		
		if( $DB->getAffectedRows() ) {
			
			$ES = ConnectionManager::getDataSource('MPSearch');
			$ES->API->update(array(
			    'index' => 'mojepanstwo_v1',
			    'type' => 'letters',
			    'id' => $id,
			    'body' => array(
				    'doc' => array(
					    'access' => $params['access'],
				    ),
			    ),
		    ));
		    
		    return true;
			
		} else return true;
		
	}
	
	public function delete($id, $params) {
		
		App::import('model','DB');
		$DB = new DB();
		
		if( is_string($id) )
			$id = array($id);
		
		foreach( $id as &$i )
			$i = addslashes( $i );
		
		$items = $DB->selectAssocs("SELECT `id`, `alphaid`, `saved` FROM `pisma_documents` WHERE `alphaid`='" . implode("' OR `alphaid`='", $id) . "' AND `from_user_type`='" . addslashes( $params['from_user_type'] ) . "' AND `from_user_id`='" . addslashes( $params['from_user_id'] ) . "'");
		
			
		if( $items ) {
			
			foreach( $items as $item ) {
			
				$DB->q("UPDATE `pisma_documents` SET `deleted`='1', `deleted_at`=NOW() WHERE `id`='" . $item['id'] . "' LIMIT 1");
				
				
				$ES = ConnectionManager::getDataSource('MPSearch');
						
				$deleteParams = array();
				$deleteParams['index'] = 'mojepanstwo_v1';
				$deleteParams['type'] = 'letters';
				$deleteParams['id'] = $item['alphaid'];
				$deleteParams['refresh'] = true;
				$deleteParams['ignore'] = array(404);
				
				$ES->API->delete($deleteParams);
			
			}
						
			return 200;
			
		} else return 404;
				
	}
	
	public function send($params) {
				
		if(
			($pismo = $this->find('first', array(
		        'conditions' => array(
			        'deleted' => '0',
			        'id' => $params['id'],
			        'from_user_type' => $params['user_type'],
			        'from_user_id' => $params['user_id'],
		        ),
	        )))
		) {
	       		       	
	        if( $params['user_type']=='account' ) {
		       	
		       	$user = $this->User->findById( $params['user_id'] );
		       	
	       	} elseif( $params['user_type']=='anonymous' ) {
		       	
		       	$user = array(
			       	'User' => array(
				       	'username' => @$params['name'] ? $params['name'] : 'Użytkownik anonimowy',
				       	'email' => $params['email'],
			       	),
		       	);
		       	
	       	}
	       	 
	    	$pismo = $pismo['Document'];	    		    	
	    	App::uses('CakeEmail', 'Network/Email');
	    		    	
			$Email = new CakeEmail('pisma');
			$Email->viewVars(array('pismo' => $pismo));
			
			if( defined('PISMA_test_email') ) {
				$to_email = PISMA_test_email;
				$to_name = PISMA_test_name;
			} else {
				$to_email = $pismo['to_email'];
				$to_name = $pismo['to_name'];
			}
						
			$status = $Email->template('Pisma.pismo', 'Pisma.layout')
				->addHeaders(array('X-Mailer' => 'mojePaństwo'))
				->emailFormat('html')
				->subject($pismo['title'])
				->to($to_email, $to_name)
				->from('pisma@mojepanstwo.pl', 'Pisma | mojePaństwo')
				->replyTo($user['User']['email'], $user['User']['username'])
				->cc($user['User']['email'], $user['User']['username'])
				->send();    	    
    	    
    	    
    	    $ES = ConnectionManager::getDataSource('MPSearch');
			
		    $ES->API->update(array(
			    'index' => 'mojepanstwo_v1',
			    'type' => 'letters',
			    'id' => $params['id'],
			    'body' => array(
				    'doc' => array(
					    'sent' => true,
				    	'sent_at' => date('Ymd\THis\Z'),
				    ),
			    ),
		    ));
    	    
    	    $db = ConnectionManager::getDataSource('default');
    	    $db->query("UPDATE `pisma_documents` SET `sent`='1', `sent_at`=NOW() WHERE `alphaid`='" . addslashes( $params['id'] ) . "'");

    	    return (boolean) $status;
	        
        } else throw new NotFoundException();
				
	}
	
	public function transfer_anonymous($anonymous_user_id, $user_id) {
				
		if(
			( $db = ConnectionManager::getDataSource('default') ) && 
			( $where = "from_user_type='anonymous' AND from_user_id='" . addslashes( $anonymous_user_id ) . "'" ) && 
			( $ids = $db->query("SELECT alphaid, saved FROM pisma_documents WHERE $where") ) 
		) {
			
			
			$db->query("UPDATE pisma_documents SET `from_user_type`='account', `from_user_id`='" . addslashes( $user_id ) . "' WHERE $where");
			
			foreach( $ids as $id )
				$this->sync($id['pisma_documents']['alphaid']);
						
			return true;
			
		} else return false;
		
	}
	
	public function syncAll() {
		
		$db = ConnectionManager::getDataSource('default');
		$ids = $db->query("SELECT alphaid FROM pisma_documents WHERE saved='1'");
		foreach( $ids as $id ) {
			
			$this->sync( $id['pisma_documents']['alphaid'] );
			
		}
		
	}
	
	public function sync($id) {
		
		$db = ConnectionManager::getDataSource('default');
	    $ES = ConnectionManager::getDataSource('MPSearch');

		$mask = "Ymd\THis\Z";

		$doc = $db->query("SELECT * FROM pisma_documents WHERE alphaid='" . addslashes( $id ) . "'");
		$doc = $doc[0]['pisma_documents'];				
						
		$data = array(
			'date' => $doc['date'],
			'to_str' => $doc['to_str'],
			'template_id' => $doc['template_id'],
			'title' => $doc['title'],
			'name' => $doc['name'],
			'content_html' => $doc['content_html'],
			'from_str' => $doc['from_str'],
			'from_signature' => $doc['from_signature'],
			'from_user_type' => $doc['from_user_type'],
			'from_user_id' => $doc['from_user_id'],
			'from_user_name' => $doc['from_user_name'],
			'to_dataset' => $doc['to_dataset'],
			'to_id' => $doc['to_id'],
			'alphaid' => $doc['alphaid'],
			'to_label' => $doc['to_name'],
			'to_email' => $doc['to_email'],
			'slug' => $doc['slug'],
			'access' => $doc['access'],
			
			'id' => $doc['id'],
			'hash' => $doc['hash'],
			'saved' => (boolean) $doc['saved'],
			'sent' => (boolean) $doc['sent'],
			'deleted' => (boolean) $doc['deleted'],
		);
		
		$data['text'] = @$doc['name'] . "\n";
		$data['text'] .= @$doc['from_str'] . "\n";
		$data['text'] .= @$doc['from_location'] . "\n";
		$data['text'] .= @$doc['date'] . "\n";
		$data['text'] .= @$doc['to_str'] . "\n";
		$data['text'] .= @$doc['title'] . "\n";
		$data['text'] .= @$doc['content'] . "\n";
		$data['text'] .= @$doc['from_signature'] . "\n";		
		
		$ts_fields = array('created_at', 'modified_at', 'saved_at', 'sent_at', 'deleted_at');
		foreach( $ts_fields as $ts_field ) {
			if( 
				isset($doc[ $ts_field ]) && 
				( $doc[$ts_field] != '0000-00-00 00:00:00' ) && 
				$ts = strtotime( $doc[$ts_field] )
			)
				$data[ $ts_field ] = date($mask, $ts);
		}		
		
		if( $data['from_user_type'] == 'account' ) {
			
			App::import('model','Paszport.User');
			$user = new User();
			$user = $this->User->findById($data['from_user_id']);
			$data['from_user_name'] = $user['User']['username'];
			$db->query("UPDATE pisma_documents SET `from_user_name`='" . addslashes( $user['User']['username'] ) . "' WHERE alphaid='" . addslashes( $id ) . "'");
			
		}
		
		if( $data['template_id'] ) {
			
			$template = $db->query("SELECT nazwa FROM pisma_szablony WHERE id='" . addslashes( $data['template_id'] ) . "'");			
			$data['template_label'] = $template[0]['pisma_szablony']['nazwa'];
			
		} else {
			$data['template_label'] = 'Bez szablonu';
		}
						
		$response = $ES->API->index(array(
			'index' => 'mojepanstwo_v1',
			'type' => 'letters',
			'id' => $data['alphaid'],
			'body' => $data,
		));
		
		
		// debug( $response );
				
	}

}
