<?php

class WgmFacebook_Controller extends DevblocksControllerExtension {
	
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}

	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
	
		@$code = DevblocksPlatform::importGPC($_REQUEST['code'], 'string', ''); 
		$url = DevblocksPlatform::getUrlService();
		$redirect_url = $url->write('ajax.php?c=config&a=handleSectionAction&section=facebook&action=auth&_callback=true&code=' . $code, true);
		
		header('Location: ' . $redirect_url);
	}

	function writeResponse(DevblocksHttpResponse $response) {
		return;
	}
	
	
}

if(class_exists('Extension_PageMenuItem')):
class WgmFacebook_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const POINT = 'wgmfacebook.setup.menu.plugins.facebook';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.facebook::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class WgmFacebook_SetupSection extends Extension_PageSection {
	const ID = 'wgmfacebook.setup.facebook';
	
	function render() {
		// check whether extensions are loaded or not
		$extensions = array(
			'oauth' => extension_loaded('oauth')
		);
		$tpl = DevblocksPlatform::getTemplateService();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'facebook');
		
		$params = array(
			'client_id' => DevblocksPlatform::getPluginSetting('wgm.facebook','client_id',''),
			'client_secret' => DevblocksPlatform::getPluginSetting('wgm.facebook','client_secret',''),
			'users' => json_decode(DevblocksPlatform::getPluginSetting('wgm.facebook', 'users', ''), TRUE),
		);
		$tpl->assign('params', $params);
		$tpl->assign('extensions', $extensions);
		
		$tpl->display('devblocks:wgm.facebook::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			@$client_id = DevblocksPlatform::importGPC($_REQUEST['client_id'],'string','');
			@$client_secret = DevblocksPlatform::importGPC($_REQUEST['client_secret'],'string','');
			
			if(empty($client_id) || empty($client_secret))
				throw new Exception("Both the API Auth Token and URL are required.");
			
			DevblocksPlatform::setPluginSetting('wgm.facebook','client_id',$client_id);
			DevblocksPlatform::setPluginSetting('wgm.facebook','client_secret',$client_secret);
			
		    echo json_encode(array('status'=>true,'message'=>'Saved!'));
		    return;
			
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
	
	function authAction() {
		@$callback = DevblocksPlatform::importGPC($_REQUEST['_callback'], 'bool', 0);
		@$post = DevblocksPlatform::importGPC($_REQUEST['_post'], 'bool', 0);
		@$code = DevblocksPlatform::importGPC($_REQUEST['code'], 'string', '');
		
		$facebook = WgmFacebook_API::getInstance();
		
		$url = DevblocksPlatform::getUrlService();
		$oauth_callback_url = $url->write('ajax.php?c=facebookauth', true);
				
		if($callback) {
			if($code) {
				$token = $facebook->getAccessToken($oauth_callback_url, $code);
				$facebook->setCredentials($token['access_token']);
				$user = $facebook->getUser();
				
				$users = json_decode(DevblocksPlatform::getPluginSetting('wgm.facebook', 'users', ''), true);
				$user = array('id' => $user['id'], 'name' => $user['name'], 'access_token' => $token['access_token']);
				$users[$user['id']] = $user;
				
				DevblocksPlatform::setPluginSetting('wgm.facebook', 'users', json_encode($users));
				DevblocksPlatform::redirect(new DevblocksHttpResponse(array('config/facebook/')));
			}
		} else {
			try {				
				$auth_url = $facebook->getAuthorizationUrl($oauth_callback_url);
				header('Location: ' . $auth_url);
//				var_dump($oauth_callback_url);
			} catch(OAuthException $e) {
				echo "Exception " . $e->getMessage();
			}
		}
	}
	
	
};
endif;

class WgmFacebook_API {
	
	const FACEBOOK_OAUTH_HOST = "https://graph.facebook.com";
	const FACEBOOK_AUTHORIZE_URL = "https://graph.facebook.com/oauth/authorize";
	const FACEBOOK_AUTHENTICATE_URL = "https://www.facebook.com/dialog/oauth";
	const FACEBOOK_ACCESS_TOKEN_URL = "https://graph.facebook.com/oauth/access_token";
	const FACEBOOK_USER_URL = "https://graph.facebook.com/me";
	
	static $_instance = null;
	private $_oauth = null;
	private $_client_id = null;
	private $_client_secret = null;
	private $_access_token = null;
	
	private function __construct() {
		$this->_client_id = DevblocksPlatform::getPluginSetting('wgm.facebook','client_id','');
		$this->_client_secret = DevblocksPlatform::getPluginSetting('wgm.facebook','client_secret','');
		$this->_oauth = new OAuth($this->_client_id, $this->_client_secret);
	}
	
	/**
	 * @return WgmFacebook_API
	 */
	static public function getInstance() {
		if(null == self::$_instance) {
			self::$_instance = new WgmFacebook_API();
		}

		return self::$_instance;
	}
	
	public function setCredentials($token) {		
		$this->_access_token = $token;
	}
	
	public function getAccessToken($callback_url, $code) {
		return $this->_oauth->getAccessToken(self::FACEBOOK_ACCESS_TOKEN_URL .
												"?client_id=" . $this->_client_id .
												"&client_secret=" . $this->_client_secret .
												"&redirect_uri=" . $callback_url .
												"&code=" . $code);
	}
	
	public function getAuthorizationUrl($callback_url) {
		return self::FACEBOOK_AUTHENTICATE_URL . "?client_id=" . $this->_client_id . "&scope=offline_access,manage_pages,publish_stream&redirect_uri=" . $callback_url;
	}
	
	public function getUser() {
		return $this->get(self::FACEBOOK_OAUTH_HOST . '/me');
	}
	
	public function postStatusMessage($user, $content) {
		$params = array(
			'message' => $content,
		);
		$this->post(self::FACEBOOK_OAUTH_HOST . '/' . $user. '/feed', $params);
	}
	
	public function post($url, $params) {
		$this->_fetch($url . '?access_token=' . $this->_access_token, 'POST', $params);
	}
	
	public function get($url) {
		return $this->_fetch($url . '?access_token=' . $this->_access_token, 'GET');
	}
	
	private function _fetch($url, $method = 'GET', $params = array()) {		
		switch($method) {
			case 'POST':
				$method = OAUTH_HTTP_METHOD_POST;
				break;
			default:
				$method = OAUTH_HTTP_METHOD_GET;
		}
		try {
			$this->_oauth->fetch($url, $params, $method);
			return json_decode($this->_oauth->getLastResponse(), true);
		} catch(OAuthException $e) {
			echo 'Exception: ' . $e->getMessage();
		}
		
	}
}

if(class_exists('Extension_DevblocksEventAction')):
class WgmFacebook_EventActionPost extends Extension_DevblocksEventAction {
	function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		$tpl->assign('token_labels', $event->getLabels());
		
		if(!is_null($seq))
			$tpl->assign('namePrefix', 'action'.$seq);
			
		$users = DevblocksPlatform::getPluginSetting('wgm.facebook', 'users', '');
		$users = json_decode($users, TRUE);
		
		$tpl->assign('users', $users);
		
		$tpl->display('devblocks:wgm.facebook::events/action_update_status_facebook.tpl');
	}
	
	function run($token, Model_TriggerEvent $trigger, $params, &$values) {
		$facebook = WgmFacebook_API::getInstance();
		
		$users = DevblocksPlatform::getPluginSetting('wgm.facebook', 'users');
		$users = json_decode($users, TRUE);
		
		$user = $users[$params['user']];

		// Translate message tokens
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		if(false !== ($content = $tpl_builder->build($params['content'], $values))) {

			$facebook->setCredentials($user['access_token']);
			$facebook->postStatusMessage($user['id'], $content);
			// POST profile_id/feed 'message' 'type'
			
		}
	}
};
endif;
