<?php
define('API_CON_TEST_ONLINE', true);

$slug = "ci-login/index.php";
$data = array(
	'client_id' => '321',
	'client_secret' => '654',
	'redirect_uri' => 'http://cityindex.loc/wp-admin/admin-ajax.php?action=api_con_mngr'
);
$options = array(
    'active_plugins' => array('api-connection-manager/index.php','autoflow/index.php'),
    'api-connection-manager' => array(
    	'active' => array($slug),
        'inactive' => array()
    ),
    'API_Con_Mngr_Module-connections' => array(
        $slug => array('1','123456')
    ),
    'API_Con_Mngr_Module' => array(
    	$slug => $data
    ),
	'test_slug' => $slug
);