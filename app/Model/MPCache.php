<?


class MPCache extends AppModel {
    
    public $useDbConfig = 'MPCache';
    public $useTable = false;
		

	public function get($key) {
		return $this->getDataSource()->get($key);
	}

	public function getObjectByDataset($dataset, $object_id) {
		
		if( 
			( $data = $this->getDataSource()->scriptQuery('getObjectByDataset', array($dataset, $object_id)) ) &&
			( $data = json_decode($data, true) )
		) 
			return $data;
		else
			return false;
				
	}
	
	public function getDataset($dataset, $full = false) {
		
		$path = $full ? 'data/datasets-full/' : 'data/datasets/';
		return json_decode( $this->getDataSource()->get($path . $dataset), true );
		
	}
	
	public function getDatasetId($dataset) {
		
		$path = 'data/datasets-map/';
		return json_decode( $this->getDataSource()->get($path . $dataset), true );
		
	}
	
	public function getApp($app, $full = false) {
		
		$path = 'data/apps/' . $app;
		return json_decode( $this->getDataSource()->get($path), true );
		
	}
	
	public function getAvailableDatasets() {
		return explode(',', $this->getDataSource()->get('data/datasets/main_search'));
	}
    
}



