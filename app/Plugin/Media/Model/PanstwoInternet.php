<?php

class PanstwoInternet extends AppModel
{

    public $useTable = false;

    public function get_annual_twitter_stats($year)
    {

        $file = 'http://admin.sejmometr.pl/_resources/twitter/stats/2013.json';
        $data = @json_decode(file_get_contents($file), true);
        return $data;

    }
    
    public function get_twitter_stats($range)
    {
		
		App::import('model', 'DB');
        $this->DB = new DB();
        
        $data = $this->DB->selectValue("SELECT `data` FROM `twitter_stats` WHERE `id`='" . addslashes( $range ) . "'");
        if( $data && ($data = unserialize(stripslashes($data))) ) {

	        return $data;	        
	        
        } return false;

    }

    public function twitter_accounts_types()
    {

        $result = $this->query("SELECT `id`, `nazwa`, `class` FROM `twitter_accounts_types` WHERE `ranking_new`='1' ORDER BY `ranking_ord` ASC");
        foreach ($result as &$r)
            $r = $r['twitter_accounts_types'];

        return $result;

    }

    public function twitter_accounts_group_by_types($range_id, $types, $order)
    {
		
		App::import('model', 'DB');
        $this->DB = new DB();
        
		$ranges = array(
			'24h' => '[NOW-1DAY+2HOUR TO *]',
			'3d' => '[NOW-3DAY+2HOUR TO *]',
			'7d' => '[NOW-7DAY+2HOUR TO *]',
			'1m' => '[NOW-1MONTH+2HOUR TO *]',
			'1y' => '[NOW-1YEAR+2HOUR TO *]',
		);
		
		$range_keys = array_keys($ranges);
		
		if( !in_array($range_id, $range_keys) )
			$range_id = $range_keys[0];
		
        foreach ($types as &$t) {
			
			$data = array();
			
			if( $order=='followers' ) {
				
				
				
				$fields = array('id', 'name', 'followers_date', 'profile_image_url', 'followers_count');
				$fields[] = 'followers_delta_' . $range_id;
				$fields[] = 'followers_add_' . $range_id;
				$fields[] = 'followers_diff_' . $range_id;
				$fields[] = 'followers_' . $range_id;
				
				$_order = 'followers_delta_' . $range_id;
				
				$q = "SELECT `" . implode("`, `", $fields) ."` FROM `twitter_accounts` WHERE `typ_id`='" . addslashes( $t ) . "' ORDER BY `" . $_order . "` DESC LIMIT 3";
				$data = $this->DB->selectAssocs($q);
				
				
				
			} elseif( $order=='defollowers' ) {
				
				
				
				$fields = array('id', 'name', 'followers_date', 'profile_image_url', 'followers_count');
				$fields[] = 'followers_delta_' . $range_id;
				$fields[] = 'followers_add_' . $range_id;
				$fields[] = 'followers_diff_' . $range_id;
				$fields[] = 'followers_' . $range_id;
				
				$_order = 'followers_delta_' . $range_id;
				
				$q = "SELECT `" . implode("`, `", $fields) ."` FROM `twitter_accounts` WHERE `typ_id`='" . addslashes( $t ) . "' ORDER BY `" . $_order . "` ASC LIMIT 3";
				$data = $this->DB->selectAssocs($q);
				
				
				
			}
			
            $t = array(
                'id' => $t,
                'search' => $data,
            );

        }

        return $types;

    }

    public function get_twitter_tweets_group_by_types($range, $types, $order)
    {
		
		$ranges = array(
			'24h' => '[NOW-1DAY+2HOUR TO *]',
			'3d' => '[NOW-3DAY+2HOUR TO *]',
			'7d' => '[NOW-7DAY+2HOUR TO *]',
			'1m' => '[NOW-1MONTH+2HOUR TO *]',
			'1y' => '[NOW-1YEAR+2HOUR TO *]',
		);
		
		$range_keys = array_keys($ranges);
		
		if( !in_array($range, $range_keys) )
			$range = $range_keys[0];
			
		
		
        foreach ($types as &$t) {

            $t = array(
                'id' => $t,
                'search' => ClassRegistry::init('Dane.Dataobject')->find('all', array(
                        'conditions' => array(
                            'dataset' => 'twitter',
                            'twitter_accounts.typ_id' => $t,
                            '!bez_retweetow' => '1',
                            'date' => $ranges[ $range ],
                        ),
                        'order' => $order,
                        'limit' => 3,
                    )),
            );

        }

        return $types;

    }

} 