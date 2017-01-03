<?php
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
		$tpl = DevblocksPlatform::getTemplateService();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'facebook');
		
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook', 'credentials', '', true, true);
		$tpl->assign('credentials', $credentials);
		
		$tpl->display('devblocks:wgm.facebook::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			@$client_id = DevblocksPlatform::importGPC($_REQUEST['client_id'],'string','');
			@$client_secret = DevblocksPlatform::importGPC($_REQUEST['client_secret'],'string','');
			
			if(empty($client_id) || empty($client_secret))
				throw new Exception("Both the API Auth Token and URL are required.");
			
			$credentials = [
				'client_id' => $client_id,
				'client_secret' => $client_secret,
			];
			
			DevblocksPlatform::setPluginSetting('wgm.facebook', 'credentials', $credentials, true, true);
			
			echo json_encode(array('status'=>true,'message'=>'Saved!'));
			return;
			
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
};
endif;

class WgmFacebook_API {
	const FACEBOOK_OAUTH_HOST = "https://graph.facebook.com";
	const FACEBOOK_AUTHORIZE_URL = "https://graph.facebook.com/oauth/authorize";
	const FACEBOOK_ACCESS_TOKEN_URL = "https://graph.facebook.com/oauth/access_token";
	
	const FACEBOOK_USER_URL = "https://graph.facebook.com/me";
	
	static $_instance = null;
	
	private $_oauth = null;
	private $_client_id = null;
	private $_client_secret = null;
	
	private function __construct() {
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook', 'credentials', '', true, true);
		
		$this->_client_id = @$credentials['client_id'];
		$this->_client_secret = @$credentials['client_secret'];
		
		$this->_oauth = DevblocksPlatform::getOAuthService($this->_client_id, $this->_client_secret);
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
		$this->_oauth->setTokens($token);
	}
	
	public function getAccessToken($callback_url, $code) {
		$url = self::FACEBOOK_ACCESS_TOKEN_URL .
			"?client_id=" . $this->_client_id .
			"&client_secret=" . $this->_client_secret .
			"&redirect_uri=" . urlencode($callback_url) .
			"&code=" . $code;
		return $this->_oauth->getAccessToken($url);
	}
	
	public function getAuthorizationUrl($callback_url) {
		return self::FACEBOOK_AUTHORIZE_URL . "?client_id=" . $this->_client_id . "&scope=public_profile,read_page_mailboxes,manage_pages,publish_pages&redirect_uri=" . urlencode($callback_url);
	}
	
	public function getUser() {
		return $this->get(self::FACEBOOK_OAUTH_HOST . '/me');
	}
	
	public function getUserAccounts() {
		return $this->get(self::FACEBOOK_OAUTH_HOST . '/me/accounts');
	}
	
	public function postStatusMessage($user, $content) {
		$params = array(
			'message' => $content,
		);
		$this->post(self::FACEBOOK_OAUTH_HOST . '/' . $user. '/feed', $params);
	}
	
	public function post($url, $params) {
		return $this->_fetch($url, 'POST', $params);
	}
	
	public function get($url) {
		return $this->_fetch($url, 'GET');
	}
	
	private function _fetch($url, $method = 'GET', $params = array()) {
		if(false == ($response = $this->_oauth->executeRequestWithToken($method, $url, $params, 'OAuth')))
			return false;
		
		return $response;
	}
}

if(class_exists('Extension_DevblocksEventAction')):
class WgmFacebook_EventActionPost extends Extension_DevblocksEventAction {
	function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		
		if(!is_null($seq))
			$tpl->assign('namePrefix', 'action'.$seq);
		
		$accounts = DAO_ConnectedAccount::getReadableByActor($trigger->getBot(), ServiceProvider_FacebookPages::ID);
		$tpl->assign('accounts', $accounts);
		
		$tpl->display('devblocks:wgm.facebook::events/action_post_to_facebook_page.tpl');
	}
	
	function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$account_id = DevblocksPlatform::importVar($params['connected_account_id'], 'int', 0);
		@$content = DevblocksPlatform::importVar($params['content'], 'string', null);
		
		$out = '';
		
		if(empty($account_id) || false == ($account = DAO_ConnectedAccount::get($account_id))) {
			return "[ERROR] No Facebook account is configured.  Skipping...";
		}
		
		if(false == ($credentials = $account->decryptParams($trigger->getBot())))
			return "[ERROR] Failed to decrypt account credentials.";
		
		if(!isset($credentials['access_token']) || !isset($credentials['id']))
			return "[ERROR] The stored credentials lack an ID or access_token.";
		
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		if(false !== ($content = $tpl_builder->build($content, $dict))) {
			$out .= sprintf(">>> Posting message to %s\n\n%s\n",
				$account->name,
				$content
			);
		}
		
		return $out;
	}
	
	function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$account_id = DevblocksPlatform::importVar($params['connected_account_id'], 'int', 0);
		@$content = DevblocksPlatform::importVar($params['content'], 'string', null);

		$facebook = WgmFacebook_API::getInstance();

		if(empty($account_id) || false == ($account = DAO_ConnectedAccount::get($account_id))) {
			return false;
		}
		
		if(false == ($credentials = $account->decryptParams($trigger->getBot())))
			return false;
		
		if(!isset($credentials['access_token']) || !isset($credentials['id']))
			return false;
		
		// Translate message tokens
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		if(false !== ($content = $tpl_builder->build($content, $dict))) {
			$facebook->setCredentials($credentials['access_token']);
			$facebook->postStatusMessage($credentials['id'], $content);
		}
	}
};
endif;

class ServiceProvider_Facebook extends Extension_ServiceProvider implements IServiceProvider_OAuth, IServiceProvider_HttpRequestSigner {
	const ID = 'wgm.facebook.service.provider';
	
	private function _getAppKeys() {
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook','credentials',[],true,true);
		
		if(!isset($credentials['client_id']) || !isset($credentials['client_secret']))
			return false;
		
		return array(
			'key' => $credentials['client_id'],
			'secret' => $credentials['client_secret'],
		);
	}
	
	function renderPopup() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		// [TODO] Report about missing app keys
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$url_writer = DevblocksPlatform::getUrlService();
		$oauth = DevblocksPlatform::getOAuthService($app_keys['key'], $app_keys['secret']);
		
		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_Facebook::ID), true) . '?view_id=' . rawurlencode($view_id);
		
		// [TODO] The scope should come from the app config
		$url = sprintf("%s?client_id=%s&scope=%s&redirect_uri=%s",
			$oauth->getAuthenticationURL(WgmFacebook_API::FACEBOOK_AUTHORIZE_URL),
			$app_keys['key'],
			'public_profile,read_page_mailboxes,manage_pages,publish_pages',
			rawurlencode($redirect_url)
		);
		
		header('Location: ' . $url);
	}
	
	// [TODO] Verify the caller?
	function oauthCallback() {
		@$code = $_REQUEST['code'];
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		$url_writer = DevblocksPlatform::getUrlService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;

		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_Facebook::ID), true) . '?view_id=' . rawurlencode($view_id);
		
		$oauth = DevblocksPlatform::getOAuthService($app_keys['key'], $app_keys['secret']);
		$oauth->setTokens($code);
		
		$url = WgmFacebook_API::FACEBOOK_ACCESS_TOKEN_URL .
			"?client_id=" . $app_keys['key'] .
			"&client_secret=" . $app_keys['secret'] .
			"&redirect_uri=" . urlencode($redirect_url) .
			"&code=" . $code
			;
			
		$params = $oauth->getAccessToken($url);
		
		$fb = WgmFacebook_API::getInstance();
		$fb->setCredentials($params['access_token']);

		if(false == ($profile_data = $fb->getUser()))
			return;
		
		if(false == ($pages = $fb->getUserAccounts()))
			return;
		
		$params['pages'] = array();
		
		if(isset($pages['data']))
		foreach($pages['data'] as $page) {
			$params['pages'][$page['id']] = array(
				'access_token' => $page['access_token'],
				'name' => $page['name'],
				//'perms' => $page['perms'],
			);
		}
		
		// [TODO] cache timestamp since tokens can expire
		
		$id = DAO_ConnectedAccount::create(array(
			DAO_ConnectedAccount::NAME => 'Facebook @' . $profile_data['name'],
			DAO_ConnectedAccount::EXTENSION_ID => ServiceProvider_Facebook::ID,
			DAO_ConnectedAccount::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
			DAO_ConnectedAccount::OWNER_CONTEXT_ID => $active_worker->id,
		));
		
		DAO_ConnectedAccount::setAndEncryptParams($id, $params);
		
		if($view_id) {
			echo sprintf("<script>window.opener.genericAjaxGet('view%s', 'c=internal&a=viewRefresh&id=%s');</script>",
				rawurlencode($view_id),
				rawurlencode($view_id)
			);
			
			C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_CONNECTED_ACCOUNT, $id);
		}
		
		echo "<script>window.close();</script>";
	}
	
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers) {
		$credentials = $account->decryptParams();
		
		if(
			!isset($credentials['access_token'])
		)
			return false;
		
		// Add a bearer token
		$headers[] = sprintf('Authorization: OAuth %s', $credentials['access_token']);
		
		return true;
	}
	
}

class ServiceProvider_FacebookPages extends Extension_ServiceProvider implements IServiceProvider_OAuth, IServiceProvider_HttpRequestSigner {
	const ID = 'wgm.facebook.pages.service.provider';
	
	private function _getAppKeys() {
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook','credentials',[],true,true);
		
		if(!isset($credentials['client_id']) || !isset($credentials['client_secret']))
			return false;
		
		return array(
			'key' => $credentials['client_id'],
			'secret' => $credentials['client_secret'],
		);
	}
	
	function renderPopup() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		// [TODO] Report about missing app keys
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$url_writer = DevblocksPlatform::getUrlService();
		$oauth = DevblocksPlatform::getOAuthService($app_keys['key'], $app_keys['secret']);
		
		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_FacebookPages::ID), true) . '?view_id=' . rawurlencode($view_id);
		
		// [TODO] The scope should come from the app config
		$url = sprintf("%s?client_id=%s&scope=%s&redirect_uri=%s",
			$oauth->getAuthenticationURL(WgmFacebook_API::FACEBOOK_AUTHORIZE_URL),
			$app_keys['key'],
			'public_profile,read_page_mailboxes,manage_pages,publish_pages',
			rawurlencode($redirect_url)
		);
		
		header('Location: ' . $url);
	}
	
	// [TODO] Verify the caller?
	function oauthCallback() {
		@$mode = $_REQUEST['mode'];
		
		if($mode == 'choosePages') {
			$this->choosePagesAction();
			return;
		}
		
		@$code = $_REQUEST['code'];
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'], 'string', '');
		
		$tpl = DevblocksPlatform::getTemplateService();
		$encrypt = DevblocksPlatform::getEncryptionService();
		$url_writer = DevblocksPlatform::getUrlService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;

		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_FacebookPages::ID), true) . '?view_id=' . rawurlencode($view_id);
		
		$oauth = DevblocksPlatform::getOAuthService($app_keys['key'], $app_keys['secret']);
		$oauth->setTokens($code);
		
		$url = WgmFacebook_API::FACEBOOK_ACCESS_TOKEN_URL .
			"?client_id=" . $app_keys['key'] .
			"&client_secret=" . $app_keys['secret'] .
			"&redirect_uri=" . urlencode($redirect_url) .
			"&code=" . $code
			;
			
		$params = $oauth->getAccessToken($url);
		
		$fb = WgmFacebook_API::getInstance();
		$fb->setCredentials($params['access_token']);

		if(false == ($pages_data = $fb->getUserAccounts()) || !isset($pages_data['data'])) {
			DevblocksPlatform::dieWithHttpError("Can't find any pages associated with this Facebook account.", 401);
			return;
		}
		
		// [TODO] page cursors
		
		$_SESSION['facebook_pages_data'] = $encrypt->encrypt(json_encode($pages_data['data']));
		
		$tpl->assign('view_id', $view_id);
		
		$pages = array_column($pages_data['data'], 'name', 'id');
		$tpl->assign('pages', $pages);
		
		$tpl->display('devblocks:wgm.facebook::setup/pick_pages.tpl');
	}
	
	function choosePagesAction() {
		@$page_ids = DevblocksPlatform::importGPC($_POST['page_ids'],'array',[]);
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string','');
		
		$encrypt = DevblocksPlatform::getEncryptionService();
		
		if(false == ($active_worker = CerberusApplication::getActiveWorker()))
			DevblocksPlatform::dieWithHttpError('Invalid session data.', 403);
		
		@$pages_data = json_decode($encrypt->decrypt($_SESSION['facebook_pages_data']), true);
		unset($_SESSION['facebook_pages_data']);
		
		if(empty($pages_data)) {
			unset($_SESSION['facebook_pages_data']);
			DevblocksPlatform::dieWithHttpError('Invalid session data.', 403);
			return;
		}
		
		$ids = [];
		
		foreach($pages_data as $page_data) {
			if(!in_array($page_data['id'], $page_ids))
				continue;
			
			$id = DAO_ConnectedAccount::create(array(
				DAO_ConnectedAccount::NAME => 'Facebook Pages: ' . $page_data['name'],
				DAO_ConnectedAccount::EXTENSION_ID => ServiceProvider_FacebookPages::ID,
				DAO_ConnectedAccount::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
				DAO_ConnectedAccount::OWNER_CONTEXT_ID => $active_worker->id,
			));
			
			$ids[] = $id;
			
			// [TODO] cache timestamp since tokens can expire
			
			DAO_ConnectedAccount::setAndEncryptParams($id, $page_data);
		}
		
		if($view_id) {
			echo sprintf("<script>window.opener.genericAjaxGet('view%s', 'c=internal&a=viewRefresh&id=%s');</script>",
				rawurlencode($view_id),
				rawurlencode($view_id)
			);
			
			if(!empty($ids))
				C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_CONNECTED_ACCOUNT, reset($ids));
		}
		
		echo "<script>window.close();</script>";
	}
	
	function authenticateHttpRequest(Model_ConnectedAccount $account, &$ch, &$verb, &$url, &$body, &$headers) {
		$credentials = $account->decryptParams();
		
		if(
			!isset($credentials['access_token'])
		)
			return false;
		
		// Add a bearer token
		$headers[] = sprintf('Authorization: OAuth %s', $credentials['access_token']);
		
		return true;
	}
}