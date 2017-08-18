<?php
if(class_exists('Extension_PageMenuItem')):
class WgmFacebook_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const POINT = 'wgmfacebook.setup.menu.plugins.facebook';
	
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.facebook::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class WgmFacebook_SetupSection extends Extension_PageSection {
	const ID = 'wgmfacebook.setup.facebook';
	
	function render() {
		$tpl = DevblocksPlatform::services()->template();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'facebook');
		
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook', 'credentials', [], true, true);
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
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook', 'credentials', [], true, true);
		
		$this->_client_id = @$credentials['client_id'];
		$this->_client_secret = @$credentials['client_secret'];
		
		$this->_oauth = DevblocksPlatform::services()->oauth($this->_client_id, $this->_client_secret);
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
		$tpl = DevblocksPlatform::services()->template();
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
		
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
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
		$tpl_builder = DevblocksPlatform::services()->templateBuilder();
		if(false !== ($content = $tpl_builder->build($content, $dict))) {
			$facebook->setCredentials($credentials['access_token']);
			$facebook->postStatusMessage($credentials['id'], $content);
		}
	}
};
endif;

class ServiceProvider_Facebook extends Extension_ServiceProvider implements IServiceProvider_OAuth, IServiceProvider_HttpRequestSigner {
	const ID = 'wgm.facebook.service.provider';
	
	function renderConfigForm(Model_ConnectedAccount $account) {
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl->assign('account', $account);
		
		$params = $account->decryptParams($active_worker);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.facebook::providers/facebook.tpl');
	}
	
	function saveConfigForm(Model_ConnectedAccount $account, array &$params) {
		@$edit_params = DevblocksPlatform::importGPC($_POST['params'], 'array', array());
		
		$active_worker = CerberusApplication::getActiveWorker();
		$encrypt = DevblocksPlatform::services()->encryption();
		
		// Decrypt OAuth params
		if(isset($edit_params['params_json'])) {
			if(false == ($outh_params_json = $encrypt->decrypt($edit_params['params_json'])))
				return "The connected account authentication is invalid.";
				
			if(false == ($oauth_params = json_decode($outh_params_json, true)))
				return "The connected account authentication is malformed.";
			
			if(is_array($oauth_params))
			foreach($oauth_params as $k => $v)
				$params[$k] = $v;
		}
		
		return true;
	}
	
	private function _getAppKeys() {
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook','credentials',[],true,true);
		
		if(!isset($credentials['client_id']) || !isset($credentials['client_secret']))
			return false;
		
		return array(
			'key' => $credentials['client_id'],
			'secret' => $credentials['client_secret'],
		);
	}
	
	function oauthRender() {
		@$form_id = DevblocksPlatform::importGPC($_REQUEST['form_id'], 'string', '');
		
		// Store the $form_id in the session
		$_SESSION['oauth_form_id'] = $form_id;
		
		// [TODO] Report about missing app keys
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$url_writer = DevblocksPlatform::services()->url();
		$oauth = DevblocksPlatform::services()->oauth($app_keys['key'], $app_keys['secret']);
		
		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_Facebook::ID), true);
		
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
		
		$form_id = $_SESSION['oauth_form_id'];
		unset($_SESSION['oauth_form_id']);
		
		$url_writer = DevblocksPlatform::services()->url();
		$encrypt = DevblocksPlatform::services()->encryption();
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_Facebook::ID), true);
		
		$oauth = DevblocksPlatform::services()->oauth($app_keys['key'], $app_keys['secret']);
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
		
		$params['profile'] = [
			'id' => $profile_data['id'],
			'name' => $profile_data['name'],
		];
		
		// Output
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('form_id', $form_id);
		$tpl->assign('label', $profile_data['name']);
		$tpl->assign('params_json', $encrypt->encrypt(json_encode($params)));
		$tpl->display('devblocks:cerberusweb.core::internal/connected_account/oauth_callback.tpl');
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
	
	function renderConfigForm(Model_ConnectedAccount $account) {
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl->assign('account', $account);
		
		$params = $account->decryptParams($active_worker);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.facebook::providers/facebook_pages.tpl');
	}
	
	function saveConfigForm(Model_ConnectedAccount $account, array &$params) {
		@$edit_params = DevblocksPlatform::importGPC($_POST['params'], 'array', array());
		
		$active_worker = CerberusApplication::getActiveWorker();
		$encrypt = DevblocksPlatform::services()->encryption();
		
		// Decrypt OAuth params
		if(isset($edit_params['params_json'])) {
			if(false == ($outh_params_json = $encrypt->decrypt($edit_params['params_json'])))
				return "The connected account authentication is invalid.";
				
			if(false == ($oauth_params = json_decode($outh_params_json, true)))
				return "The connected account authentication is malformed.";
			
			if(is_array($oauth_params))
			foreach($oauth_params as $k => $v)
				$params[$k] = $v;
		}
		
		return true;
	}
	
	private function _getAppKeys() {
		$credentials = DevblocksPlatform::getPluginSetting('wgm.facebook','credentials',[],true,true);
		
		if(!isset($credentials['client_id']) || !isset($credentials['client_secret']))
			return false;
		
		return array(
			'key' => $credentials['client_id'],
			'secret' => $credentials['client_secret'],
		);
	}
	
	function oauthRender() {
		@$form_id = DevblocksPlatform::importGPC($_REQUEST['form_id'], 'string', '');
		
		// Store the $form_id in the session
		$_SESSION['oauth_form_id'] = $form_id;
		
		// [TODO] Report about missing app keys
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;
		
		$url_writer = DevblocksPlatform::services()->url();
		$oauth = DevblocksPlatform::services()->oauth($app_keys['key'], $app_keys['secret']);
		
		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_FacebookPages::ID), true);
		
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
		
		if($mode == 'choosePage') {
			$this->choosePageAction();
			return;
		}
		
		@$code = $_REQUEST['code'];
		
		unset($_SESSION['facebook_pages_data']);
		
		$tpl = DevblocksPlatform::services()->template();
		$encrypt = DevblocksPlatform::services()->encryption();
		$url_writer = DevblocksPlatform::services()->url();
		$active_worker = CerberusApplication::getActiveWorker();
		
		if(false == ($app_keys = $this->_getAppKeys()))
			return false;

		// OAuth callback
		$redirect_url = $url_writer->write(sprintf('c=oauth&a=callback&ext=%s', ServiceProvider_FacebookPages::ID), true);
		
		$oauth = DevblocksPlatform::services()->oauth($app_keys['key'], $app_keys['secret']);
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
		
		if($view_id)
			$tpl->assign('view_id', $view_id);
		
		$pages = array_column($pages_data['data'], 'name', 'id');
		$tpl->assign('pages', $pages);
		
		$tpl->display('devblocks:wgm.facebook::setup/pick_page.tpl');
	}
	
	function choosePageAction() {
		@$page_id = DevblocksPlatform::importGPC($_POST['page_id'],'string','');
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string','');
		
		$encrypt = DevblocksPlatform::services()->encryption();
		
		$form_id = $_SESSION['oauth_form_id'];
		unset($_SESSION['oauth_form_id']);
		
		if(false == ($active_worker = CerberusApplication::getActiveWorker()))
			DevblocksPlatform::dieWithHttpError('Invalid session data.', 403);
		
		@$pages_data = json_decode($encrypt->decrypt($_SESSION['facebook_pages_data']), true);
		unset($_SESSION['facebook_pages_data']);
		
		if(empty($pages_data) || !is_array($pages_data)) {
			DevblocksPlatform::dieWithHttpError('Invalid session data.', 403);
			return;
		}
		
		foreach($pages_data as $page_data) {
			if($page_data['id'] == $page_id) {
				// Output
				$tpl = DevblocksPlatform::services()->template();
				$tpl->assign('form_id', $form_id);
				$tpl->assign('label', $page_data['name']);
				$tpl->assign('params_json', $encrypt->encrypt(json_encode($page_data)));
				$tpl->display('devblocks:cerberusweb.core::internal/connected_account/oauth_callback.tpl');
				break;
			}
		}
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