<?

Router::connect('/dane/dataset/:alias/:object_id/layers/:layer', array('plugin' => 'Dane', 'controller' => 'dataobjects', 'action' => 'layer'));

Router::connect('/dane/dataset/:alias', array('plugin' => 'Dane', 'controller' => 'datasets', 'action' => 'info'));
Router::connect('/dane/dataset/:alias/:action', array('plugin' => 'Dane', 'controller' => 'datasets'));

Router::connect('/dane/datachannel/:alias', array('plugin' => 'Dane', 'controller' => 'datachannels', 'action' => 'info'));
Router::connect('/dane/datachannel/:alias/:action', array('plugin' => 'Dane', 'controller' => 'datachannels'));