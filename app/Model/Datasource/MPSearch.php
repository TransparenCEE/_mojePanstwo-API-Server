<?
class MPSearch {
	const RESULTS_COUNT_DEFAULT = 50;
	const RESULTS_COUNT_MAX = 50;

    public $cacheSources = true;
    public $description = 'Serwer wyszukania platformy mojePaństwo';
	private $_index = 'mojepanstwo_v1';    

	public $API;
    public $lastResponseStats = false;
    
    public $Aggs = array();
    private $aggs_allowed = array(
	    'date_histogram' => array('field', 'interval', 'format'),
	    'terms' => array('field', 'include', 'exclude', 'size'),
	    'range' => array('field', 'ranges'),
	    'sum' => array('field'),
	    'nested' => array('path'),
	    'aggs' => array(),
	    'global' => array(),
	    'filter' => array('term'),
    );
    
    public function query(){
	    return null;
    }
    
    public function getSchemaName()
    {
        return null;
    }
	
    public function __construct($config)
    {

        require_once(APP . 'Vendor' . DS . 'autoload.php');
        $this->API = new Elasticsearch\Client(array(
	    	'hosts' => array(
	    		$config['host'] . ':' . $config['port'],
	    	),
	    ));

    }
    
    public function doc2object($doc, $fields = null) {
		$dataset = $doc['fields']['dataset'][0];
		$id = $doc['fields']['id'][0];

		if ($dataset == null or $id == null) {
			throw new InternalErrorException("Empty dataset or id: " . $dataset . ' ' . $id);
		}

	    $output = array_merge(array(
            'global_id' => $doc['_id'],
            'dataset' => $dataset,
    		'id' => $id,
			'url' => Dataobject::apiUrl($dataset, $id),
			'mpurl' => Dataobject::mpUrl($dataset, $id),
    		'slug' => $doc['fields']['slug'][0],
            'score' => $doc['_score'],
    	), $doc['fields']['source'][0]['data']);

		// filter fields
		if ($fields != null) {
			if (!is_array($fields)) {
				// specyfing one field without array notation is ok
				$fields = array($fields);
			}

			$output = array_intersect_key($output, array_flip($fields));
		}

    	if( 
	    	isset( $doc['fields']['source'][0]['static'] ) && 
	    	!empty( $doc['fields']['source'][0]['static'] )
    	) {
	    	
			$output['static'] = $doc['fields']['source'][0]['static'];
	    	
	    }
    	
    	

    	
    	if( 
	    	isset( $doc['fields']['source'][0]['contexts'] ) && 
	    	!empty( $doc['fields']['source'][0]['contexts'] )
    	) {
	    	
	    	$force_context = false;
	    	
	    	if(
	    		isset($doc['inner_hits']) && 
	    		isset($doc['inner_hits']['alert-data']) && 
	    		isset($doc['inner_hits']['alert-data']['hits']) && 
	    		isset($doc['inner_hits']['alert-data']['hits']['total']) && 
	    		$doc['inner_hits']['alert-data']['hits']['total'] && 
	    		isset( $doc['inner_hits']['alert-data']['hits']['hits'][0]['fields']['context'][0] )
	    	) {
		    			    	
		    	$force_context = $doc['inner_hits']['alert-data']['hits']['hits'][0]['fields']['context'][0];
		    	
		    }
	    		    	
	    	$context = array();
    		foreach( $doc['fields']['source'][0]['contexts'] as $key => $value ) {
	    		
	    		if( 
		    		!$force_context || 
	    			( $force_context && (strpos($key, $force_context)!==false) )
    			) {
	    		
		    		$key_parts = explode('.', $key);
		    		$value_parts = explode("\n\r", $value);
		    		
		    		$context[] = array(
			    		'creator' => array(
				    		'dataset' => $key_parts[0],
				    		'id' => $key_parts[1],
				    		'global_id' => $value_parts[0],
				    		'name' => $value_parts[1],
				    		'slug' => $value_parts[2],
				    		'url' => @$value_parts[5],
			    		),
			    		'action' => $key_parts[2],
			    		'label' => $value_parts[3],
			    		'sentence' => $value_parts[4],
		    		);
	    		
	    		}
	    		
    		}
    		$output['contexts'] = $context;
    	
    	}
    	
    	if( 
    		isset( $doc['highlight']['text'] ) && 
    		is_array( $doc['highlight']['text'] ) && 
    		isset( $doc['highlight']['text'][0] )
    	)
    		$output['highlight'] = array($doc['highlight']['text']);
    	
    	if(
    		isset($doc['inner_hits']) && 
    		isset($doc['inner_hits']['inner']) && 
    		isset($doc['inner_hits']['inner']['hits']) && 
    		isset($doc['inner_hits']['inner']['hits']['hits']) 
    	) {
	    	
	    	foreach( $doc['inner_hits']['inner']['hits']['hits'] as $hit ) {
		    	
		    	$output['inner_hits'][] = array(
			    	'id' => $hit['_id'],
			    	'title' => @$hit['fields']['title'][0],
		    	);
		    	
	    	}	    	
    	}
    	
    	return $output;
	    
    }	
	
	public function buildESQuery( $queryData = array() ) {
		if( !isset($queryData['conditions']) )
			$queryData['conditions'] = array();
		
		if( !isset($queryData['page']) )
			$queryData['page'] = 1;

		if( !isset($queryData['limit']) || !is_numeric(@$queryData['limit'])) {
			$queryData['limit'] = self::RESULTS_COUNT_DEFAULT;

		} else if ( intval($queryData['limit']) > self::RESULTS_COUNT_MAX) {
			$queryData['limit'] = self::RESULTS_COUNT_MAX;
		}
		
		$from = ( $queryData['page'] - 1 ) * $queryData['limit'];
		$size = $queryData['limit'];
		
		$params = array(
			'index' => $this->_index,
			'type' => 'objects',
			'body' => array(
				'from' => $from, 
				'size' => $size,
				'query' => array(
					'function_score' => array(
		        		'query' => array(),
		        		'field_value_factor' => array(
							'field' => 'weights.main.score'
				        ),
		        	),
				),
				'fields' => array('dataset', 'id', 'slug'),
				'partial_fields' => array(
					'source' => array(
						'include' => array('data', 'static'),
					),
				),
				'sort' => array(
					array(
						'date' => 'desc',
					),
					array(
						'title' => 'asc',
					)
				),
			),
		);
		
		// debug($queryData); die();
		
		if( isset($queryData['order']) && is_array($queryData['order']) ) {
			
			$sort = array();
			foreach( $queryData['order'] as $os) {
				if( is_array($os) ) {
					foreach( $os as $o ) {
						
						$parts = explode(' ', $o);
						$partsCount = count( $parts );
						
						$field = false;
						$direction = 'desc';
						
						if( $partsCount===1 )
							$field = $o;
						elseif( $partsCount===2 )
							list($field, $direction) = $parts;
						
						if( $field ) {
							
							if( $field=='weight' ) {
								
								$field = 'weights.main.score';
								
							} elseif( $field=='_title' ) {
								
								$field = 'title.raw';
								
							}
							
							$sort[] = array(
								$field => $direction,
							);
							
						}
						
					}
				}
			}
			
			if( !empty($sort) )
				$params['body']['sort'] = $sort;
			
		}
		
		if( isset( $queryData['aggs'] ) ) {
			
			// debug( $queryData['aggs'] );
			$aggs = array();
						
			foreach( $queryData['aggs'] as $agg_id => $agg_data ) {
				
				if( 
					( $agg_id === '_channels' ) && 
					isset( $queryData['conditions']['_feed'] )
				) {
														
					$aggs['_channels'] = array(
	                    'global' => new \stdClass(),
	                    'aggs' => array(
	                        'feed_data' => array(
	                            'nested' => array(
	                                'path' => 'feeds_channels',
	                            ),
	                            'aggs' => array(
	                                'feed' => array(
	                                    'filter' => array(
	                                        'and' => array(
	                                            'filters' => array(
	                                                array(
	                                                    'term' => array(
	                                                        'feeds_channels.dataset' => $queryData['conditions']['_feed']['dataset'],
	                                                    ),
	                                                ),
	                                                array(
	                                                    'term' => array(
	                                                        'feeds_channels.object_id' => $queryData['conditions']['_feed']['object_id'],
	                                                    ),
	                                                )
	                                            ),
	                                        ),
	                                    ),
	                                    'aggs' => array(
	                                        'channel' => array(
	                                            'terms' => array(
	                                                'field' => 'feeds_channels.channel',
	                                                'size' => 100,
	                                            ),
	                                        ),
	                                    ),
	                                ),
	                            ),
	                        ),
	                    ),
	                );
	                
	                $this->Aggs[ '_channels' ][ 'global' ] = array();
	            
				} else {
					
					array_walk_recursive($agg_data, function(&$item, $key){
						if( $item === '_empty' )
							$item = new \stdClass();
					});
														
					foreach( $agg_data as $agg_type => $agg_params ) {
						
						// if( in_array($agg_type, array_keys($this->aggs_allowed)) ) {
															
							$this->Aggs[ $agg_id ][ $agg_type ] = $agg_params;
							$es_params = array();
							
							if( $agg_type=='global' ) {
								
								$aggs[ $agg_id ]['global'] = new \stdClass();
								
								
							} else {
							
								foreach( $agg_params as $key => $value ) {
									// if( ($agg_type == 'aggs') || in_array($key, $this->aggs_allowed[$agg_type]) ) {
										
										if( 
											( $key == 'field' ) && 
											!in_array($value, array('date', 'dataset'))
										) {
											$value = 'data.' . $value;
										}
										
										$es_params[ $key ] = $value;
									
									// }
								}
							
							}
													
							if( !empty($es_params) )
								$aggs[ $agg_id ][ $agg_type ] = $es_params;
						
						// }
						
					}
				
				}
			}
							
			if( !empty($aggs) )
				$params['body']['aggs'] = $aggs;
						
		}
		
				
		$and_filters = array();
        
                
        if(
        	(
        		!isset($queryData['conditions']['dataset']) || 
				empty($queryData['conditions']['dataset'])
			) &&
        	(
        		!isset($queryData['conditions']['_feed']) || 
	        	empty($queryData['conditions']['_feed']) 
	        )
        ) {
	        $and_filters[] = array(
	    		'term' => array(
	    			'weights.main.enabled' => true,
	    		),
	    	);
    	}
        
        // debug($queryData['conditions']);
        
        foreach( $queryData['conditions'] as $key => $value ) {
        	
        	$operator = '=';
        	if(
	        	( $key_length = strlen($key) ) && 
	        	( @substr($key, -2) === '!=' )
        	) {
	        	
	        	$operator = '!=';
	        	$key = @substr($key, 0, $key_length-2);
	        	
        	}
        	 
        	if( in_array($key, array('dataset', 'id')) ) {
        		
        		$filter_type = is_array($value) ? 'terms' : 'term';
        		$and_filters[] = array(
	        		$filter_type => array(
	        			$key => $value,
	        		),
	        	);
        	        		        		
        	} elseif( $key == 'q' ) {
				
				if( $value ) {
					
		        	$params['body']['query']['function_score']['query']['filtered']['query']['multi_match'] = array(
			        	'query' => mb_convert_encoding($value, 'UTF-8', 'UTF-8'),
					    'type' => "phrase",
					    'fields' => array('title', 'title.suggest', 'acronym', 'text'),
						'analyzer' => 'pl',
						'slop' => 5,
		        	);
		        	
		        	unset( $params['body']['sort'] );
	        	
	        	}
        	
        	} elseif( $key == '_object' ) {
				
				if( $value && ($parts = explode('.', $value)) && (count($parts)>1) ) {
						
					$and_filters[] = array(
						'nested' => array(
							'path' => 'dataobjects',
							'filter' => array(
								'bool' => array(
									'must' => array(
										array(
											'term' => array('dataobjects.dataset' => $parts[0]),
										),
										array(
											'term' => array('dataobjects.object_id' => $parts[1]),
										),
									),
								),
							),
						),
					);
	        	
	        	}        	
        	
        	} elseif( $key == 'feeds_channels' ) {
        		        		
        		$and_filters[] = array(
	        		'nested' => array(
	        			'path' => 'feeds_channels',
	        			'filter' => array(
		        			'and' => arraY(
			        			'filters' => array(
				        			array(
					        			'term' => array(
						        			'feeds_channels.dataset' => $value['dataset'],
					        			)
				        			),
				        			array(
					        			'term' => array(
						        			'feeds_channels.object_id' => $value['object_id'],
					        			)
				        			),
				        			array(
					        			'term' => array(
						        			'feeds_channels.channel' => $value['channel'],
					        			)
				        			),
			        			),
		        			),
	        			),
        			),
	        	);
	        	        	        	
        	} elseif( $key == '_feed' ) {
        		        		
        		if(
	        		isset($value['user_type']) && 
        			isset($value['user_id']) && 
        			is_numeric($value['user_id'])
        		) {
	        		
	        		$and_filters[] = array(
		        		'range' => array(
			        		'date' => array(
				        		'lte' => 'now',
			        		),
		        		),
	        		);
	        		
	        		$and_filters[] = array(
	        			'has_child' => array(
		        			'type' => 'objects-alerts',
		        			'filter' => array(
			        			'and' => arraY(
				        			'filters' => array(
					        			array(
						        			'term' => array(
							        			'user_type' => $value['user_type'],
						        			),
					        			),
					        			array(
						        			'term' => array(
							        			'user_id' => $value['user_id'],
						        			),
					        			),
				        			),
			        			),
		        			),
		        			'inner_hits' => array(
			        			'name' => 'alert-data',
				        		'fields' => array('objects-alerts.sub_id', 'objects-alerts.context', 'objects-alerts.read', 'objects-alerts.created'),
			        		),
	        			),
        			);
        			
        			$params['body']['partial_fields']['source']['include'][] = 'contexts.*';
	        		
	        		
        		} elseif (
        			isset($value['dataset']) && 
        			isset($value['object_id']) && 
        			is_numeric($value['object_id'])
        		) {
	        		
	        		$_and_filters = array(
	        			array(
	        				'term' => array(
		        				'feeds_channels.dataset' => $value['dataset'],
		        			),
	        			),
	        			array(
	        				'term' => array(
		        				'feeds_channels.object_id' => (int) $value['object_id'],
		        			),
	        			),
        			);
        			        			
        			if (
	        			isset($value['channel']) && 
	        			$value['channel'] 
	        		) {
	        			
	        			if( is_numeric($value['channel']) ) {
	        			
		        			$_and_filters[] = array(
			        			'term' => array(
				        			'feeds_channels.channel' => $value['channel'],
			        			),
		        			);
	        			
	        			} elseif( is_array($value['channel']) ) {
		        			
		        			$_and_filters[] = array(
			        			'terms' => array(
				        			'feeds_channels.channel' => $value['channel'],
			        			),
		        			);
		        			
	        			}
	        		
	        		}
	        		
        			$and_filters[] = array(
	        			'nested' => array(
		        			'path' => 'feeds_channels',
		        			'filter' => array(
			        			'and' => arraY(
				        			'filters' => $_and_filters,
			        			),
		        			),
	        			),
        			);
	        		
		        	$params['body']['partial_fields']['source']['include'][] = 'contexts.' . $value['dataset'] . '.' . $value['object_id'] . '.*';
		        	
		        	if( $value['dataset']=='rady_druki' ) {
		        	
			        	$params['body']['sort'] = array(
				        	'date' => 'asc',
				        	'feed_dataset_order.rady_druki' => 'asc',
			        	);
		        	
		        	}
		        	
		        	// unset( $params['body']['sort'] );
	        			        			
        		}	        	
	        	
	        } elseif( in_array($key, array('date', '_date')) ) {
	        		        		        	
	        	$_value = strtoupper($value);	        	
	        	
	        	$key = 'date';
	        	
	        	if( $_value == 'LAST_24H' ) {   		
						
					$range = array(
						'gte' => 'now-1d',
					);
					
					$and_filters[] = array(
						'range' => array(
							'date' => $range,
						),
					);
				
				} elseif( $_value == 'LAST_1D' ) {   		
					
					$range = array(
						'gte' => 'now-1d',
					);
					
					$and_filters[] = array(
						'range' => array(
							$key => $range,
						),
					);
				
				} elseif( $_value == 'LAST_3D' ) {   		
					
					$range = array(
						'gte' => 'now-3d',
					);
					
					$and_filters[] = array(
						'range' => array(
							$key => $range,
						),
					);
				
				} elseif( $_value == 'LAST_7D' ) {   		
					
					$range = array(
						'gte' => 'now-7d',
					);
					
					$and_filters[] = array(
						'range' => array(
							$key => $range,
						),
					);
				
				} elseif( $_value == 'LAST_1M' ) {   		
					
					$range = array(
						'gte' => 'now-1M',
					);
					
					$and_filters[] = array(
						'range' => array(
							$key => $range,
						),
					);
				
				} elseif( $_value == 'LAST_1Y' ) {   		
					
					$range = array(
						'gte' => 'now-1Y',
					);
					
					$and_filters[] = array(
						'range' => array(
							$key => $range,
						),
					);
				
				} elseif( preg_match('^\[(.*?) TO (.*?)\]^i', $_value, $match) ) {
					
					$range = array();
					
					if( ($gte = $this->formatDate( $match[1] )) && ($gte != '*') )
						$range['gte'] = strtolower( $gte );
					if( ($lte = $this->formatDate( $match[2] )) && ($lte != '*') )
						$range['lte'] = strtolower( $lte );

					
					$and_filters[] = array(
						'range' => array(
							$key => $range,
						),
					);
					
				} else {
					
					$and_filters[] = array(
		        		'term' => array(
		        			$key => $value,
		        		),
		        	);
		        	
		        }
        	
        	} elseif( $key == 'subscribtions' ) {
	        	
	        	$and_filters[] = array(
	        		'has_child' => array(
	        			'type' => '.percolator',
	        			'filter' => array(
		        			'and' => array(
			        			'filters' => array(
				        			array(
					        			'term' => array(
						        			'user_type' => $value['user_type'],
					        			),
				        			),
				        			array(
					        			'term' => array(
						        			'user_id' => $value['user_id'],
					        			),
				        			),
			        			),
		        			),
	        			),
	        			'inner_hits' => array(
		        			'name' => 'inner',
			        		'fields' => array('id', 'title', 'url'),
		        		),
	        		),
	        	);
        	
        	} else {
	        	
	        	
	        	if( preg_match('^\[(.*?)(\s*)TO(\s*)(.*?)\]^i', $value, $match) ) {
		        			        	
		        	$range = array();
					
					if( $match[1]!=='' )
						$range['gte'] = $match[1];
					if( $match[4]!=='' )
						$range['lte'] = $match[4];
					
					$and_filters[] = array(
						'range' => array(
							'data.' . $key => $range,
						),
					);
							        	
	        	} else {
	        		
	        		if( $operator==='=' ) {
		        		 	
		        		$and_filters[] = array(
				        	'term' => array(
					        	'data.' . $key => $value,
				        	),
			        	);
		        	
		        	} elseif( $operator==='!=' ) {
			        	
			        	$and_filters[] = array(
				        	'not' => array(
					        	'term' => array(
						        	'data.' . $key => $value,
					        	),
				        	),
			        	);
			        	
		        	}
	        	
	        	}
	        	
        	}

        }
		

		// debug($params); die();
		$params['body']['query']['function_score']['query']['filtered']['filter']['and']['filters'] = $and_filters;
		
		
		
		
		if( 
			isset($queryData['highlight']) && 
			$queryData['highlight'] && 
			isset($queryData['conditions']) && 
			$queryData['conditions']
		) {
			
			$params['body']['highlight'] = array(
	    		'fields' => array(
	    			'text' => array(
	    				'index_options' => 'offsets',
	    				'number_of_fragments' => 1,
	    				'fragment_size' => 160,
	    			),
	    		),
	    		
	    	);
			
		}
		
		// debug($params); die();
		
		return $params;
		
	}
	
	public function suggest($q, $options = array()) {
				
		$params = array(
			'index' => $this->_index,
			'body' => array(
				'suggest' => array(
					'text' => $q,
					'completion' => array(
						'field' => 'suggest_v6',
						'fuzzy' => array(
			                'fuzziness' => 0,
			            ),
						'context' => array(
							'dataset' => '*',
						),
					),
				),
			),
		);
		
		if( isset($options['dataset']) )
			$params['body']['suggest']['completion']['context']['dataset'] = $options['dataset'];

		$response = $this->API->suggest($params);
		return $response['suggest'][0];

	}

	public function read(Model $model, $queryData = array()) {
		$params = $this->buildESQuery($queryData);

		$this->lastResponseStats = null;
		$response = $this->API->search( $params ); 
				
        $this->lastResponseStats = array();
		if (isset($response['hits']['total'])) {
			$this->lastResponseStats['count'] = $response['hits']['total'];
		}
		if (isset($response['took'])) {
			$this->lastResponseStats['took_ms'] = $response['took'];
		}
        
        if( !empty($this->Aggs) ) {
	        
	        foreach( $this->Aggs as $agg_id => &$agg_data ) {
			    
		        if( isset($response['aggregations'][$agg_id]) )
		        	$agg_data = $response['aggregations'][$agg_id];
			        			        
	        }
	        
	        unset( $this->lastResponse['aggregations'] );
	        
        }
                
        $hits = $response['hits']['hits'];
        for( $h=0; $h<count($hits); $h++ ) 
        	$hits[$h] = $this->doc2object( $hits[$h], @$queryData['fields'] );
        
        return $hits;        

    }

	private function formatDate( $inp ) {
		
		if( $inp == '*' ) {
			return false;
		} elseif( in_array($inp, array('NOW/DAY')) ) {
			return 'now';
		} else {
			return $inp;
		}
		
	}

}