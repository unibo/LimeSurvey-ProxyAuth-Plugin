<?php

class UniboGroupsAuth extends LimeSurvey\PluginManager\AuthPluginBase {
    protected $storage = 'DbStorage';
    static protected $description = 'Groups authorizzation for Unibo';
    static protected $name = 'UniboGroupsAuth';
    static protected $aInitialPermissions = array(
        'admin' => array(
            'superadmin' => array('create', 'read')
        ),
        'servicedesk' => array(
            'participantpanel' => array('read', 'export'),
            'labelsets' => array('create', 'read', 'update', 'delete', 'import', 'export'),
            'settings' => array('read'),
            'surveysgroups' => array('create', 'read', 'update', 'delete'),
            'surveys' => array('create', 'read', 'update', 'delete', 'export'),
            'templates' => array('read', 'update', 'import', 'export'),
            'usergroups' => array(),
            'users' => array('create', 'read', 'update', 'delete'),
            'superadmin' => array()
        ),
        'editor' => array(
            'participantpanel' => array(),
            'labelsets' => array('read'),
            'settings' => array(),
            'surveysgroups' => array(),
            'surveys' => array('update'),
            'templates' => array(),
            'usergroups' => array(),
            'users' => array(),
            'superadmin' => array()
        )
    );

    
    protected $settings = array(
        'userHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_USER',
            'label' => 'Key to use for username',
            'help'=>'Will be searched in $_SERVER variable',
        ),
        'emailHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_EMAIL',
            'label' => 'Key to use for email',
            'help' => 'Will be searched in $_SERVER variable',
        ),
        'groupsHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_GROUPS',
            'label' => 'Key to use for groups',
            'help' => 'Will be searched in $_SERVER variable',
        ),
        'firstNameHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_FIRSTNAME',
            'label' => 'Key to use for first name',
            'help' => 'Will be searched in $_SERVER variable',
        ),
        'lastNameHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_LASTNAME',
            'label' => 'Key to use for last name',
            'help' => 'Will be searched in $_SERVER variable',
        ),
        'languageHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_LANGUAGE',
            'label' => 'Key to use for language',
            'help' => 'Will be searched in $_SERVER variable',
        ),
        'adminGroups' => array(
            'type' => 'string',
            'default' => 'limesurvey_admin',
            'label' => 'Group names allowed to access as admin',
            'help' => 'Comma separated list of admin groups'
        ),
        'serviceDeskGroups' => array(
            'type' => 'string',
            'default' => 'limesurvey_servicedesk',
            'label' => 'Group names allowed to access as Service Desk',
            'help' => 'Comma separated list of Service Desk groups'
        ),
        'editorGroups' => array(
            'type' => 'string',
            'default' => 'limesurvey_editor',
            'label' => 'Group names allowed to access as editor',
            'help' => 'Comma separated list of editor groups'
        )
    );

    public function init() {
        $this->subscribe('beforeActivate');
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeSurveyPage');
        $this->subscribe('beforeLogin');
        $this->subscribe('newUserSession');
    }

    public function beforeActivate()
    {
        foreach (self::$aInitialPermissions as $sRoleName => $sRolePermissions){
            //$this->printLog("dentro");
            $oRole = Permissiontemplates::model()->findByAttributes(array('name' => $sRoleName));
            if (!isset($oRole)){
                //$this->printLog($oRole);
                $oRole = new Permissiontemplates();
                $oRole->name = $sRoleName;
                $oRole->description = $sRoleName . " role";
                $oRole->created_by = App()->user->id;
                $oRole->created_at = date('Y-m-d H:i');
                $oRole->renewed_last = date('Y-m-d H:i');
                //$this->printLog($oRole->save());
                if (!$oRole->save()) return;
            }        
            foreach ($sRolePermissions as $sPermissionName => $aPermissions){
                $oPermission = Permission::model()->findByAttributes(array('permission' => $sPermissionName, 'entity_id' => $oRole->ptid, 'entity' => 'role'));
                if (!isset($oPermission)){
                    $oPermission = new Permission();
                    $oPermission->entity = "role";
                    $oPermission->entity_id = $oRole->ptid;
                    $oPermission->uid = 0;
                    $oPermission->permission = $sPermissionName;
                }
                //$this->printLog($oPermission);
                foreach(['create', 'read', 'update', 'delete', 'import', 'export'] as $sPermission){
                    if (in_array($sPermission, $aPermissions)){
                        $oPermission->{$sPermission . "_p"} = 1;
                    }
                    else{
                        $oPermission->{$sPermission . "_p"} = 0;
                    }
                }
                //$this->printLog($oPermission->save());
                if (!$oPermission->save()) return;
            }
            
        }
    }
    /**
    * Add setting on survey level: groupsToCheck to allow survey access
    */
    public function beforeSurveySettings()
    {
        $oEvent = $this->event;
        $oEvent->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'groupRequired' => array(
                    'type' => 'string',
                    'label' => 'Group names',
                    'help'=>'Comma separated group names that must be present in groupHeader in order to allow survey access',
                    'current'=> $this->get('groupRequired','Survey',$oEvent->get('survey')),
                )
            )
        ));
    }


    public function newSurveySettings()
    {
        $oEvent = $this->event;
        foreach ($oEvent->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default=$oEvent->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $oEvent->get('survey'),$default);
        }
    }

    private function groupsFromString($sGroups)
    {
        $aGroups = explode(",", $sGroups);
        foreach( $aGroups as &$sGroup)
        {
            $sGroup = trim($sGroup);
        }
        return $aGroups;
    }

    private function getSettingOrDefault($settingsName)
    {
        return $this->get($settingsName, null, null, $this->settings[$settingsName]['default']);
    }

    private function getUserDetails()
    {
        $sUserHeader = $this->getSettingOrDefault('userHeader');
        $sEmailHeader = $this->getSettingOrDefault('emailHeader');
        $sFirstNameHeader = $this->getSettingOrDefault('firstNameHeader');
        $sLastNameHeader = $this->getSettingOrDefault('lastNameHeader');
        $sLanguageHeader = $this->getSettingOrDefault('languageHeader');
        $sUser = $_SERVER[$sUserHeader] ?? '';
        $sEmail = $_SERVER[$sEmailHeader] ?? $sUser;
        $sFirstName = $_SERVER[$sFirstNameHeader] ?? '';
        $sLastName = $_SERVER[$sLastNameHeader] ?? '';
        $sLanguage = $_SERVER[$sLanguageHeader] ?? '';
        if (empty($sEmail))
        {
            array();
        }
        return array(
            "email" => $sEmail,
            "firstname" => $sFirstName,
            "lastname" => $sLastName,
            "language" => $sLanguage
        );
    }

    private function getUserProfile()
    {
        $aUSerDetails = $this->getUserDetails();
        return array(
            "email" => $aUSerDetails["email"],
            "full_name" => $aUSerDetails["firstname"] . " " . $aUSerDetails["lastname"],
            "language" => $aUSerDetails["language"],
        );
    }

    private function unrestricted_add_participant($iSurveyID, &$aParticipant)
    {
        // Backported from application/helpers/remotecontrol/remotecontrol_handle.php removing permission check
        $iSurveyID = (int) $iSurveyID;
        $oSurvey = Survey::model()->findByPk($iSurveyID);
        if (is_null($oSurvey))
        {
            return array('status' => 'Error: Invalid survey ID');
        }
        if (!Yii::app()->db->schema->getTable($oSurvey->tokensTableName))
        {
            return array('status' => 'No survey participants table');
        }
        $aDestinationFields = array_flip(Token::model($iSurveyID)->getMetaData()->tableSchema->columnNames);
        $token = Token::create($iSurveyID);
        $token->setAttributes(array_intersect_key($aParticipant, $aDestinationFields));
        $token->generateToken();
        if ($token->encryptSave(true))
        {
            $aParticipant = $token->getAttributes();
        }
        else 
        {
            $aParticipant["errors"] = $token->errors;
        }
        return $token;
    }

    private function printLog($obj)
    {
	print("<pre>");
	//$this->printLog($obj);
	print("</pre>");
    }

    public function beforeSurveyPage()
    {
        $oEvent = $this->getEvent();
        $iSurveyId = $oEvent->get('surveyId');
        $oSurvey = Survey::model()->findByPk($iSurveyId);
        $token = trim($_REQUEST["token"] ?? '');
	    //TODO: Better understand what is previewmode and how it fits the equation
        //$previewmode = Yii::app()->getConfig('previewmode');
        $previewmode = false;
	if ($oSurvey->hasTokensTable && tableExists("{{tokens_" . $iSurveyId . "}}") && (!isset($token) || $token == "") && !$previewmode) {
            $sGroupsRequired = $this->get('groupRequired', 'Survey', $iSurveyId);
            // No required groups for this survey = no automatic token creation
            if(is_null($sGroupsRequired)) return;
            $sGroupHeader = $this->getSettingOrDefault('groupsHeader');
            $Groups = $this->groupsFromString($_SERVER[$sGroupHeader] ?? '');
            foreach ($this->groupsFromString($sGroupsRequired) as $sGroupRequired)
	        {
                if (in_array($sGroupRequired, $Groups))
		        {
                    $aParticipant = $this->getUserDetails();
                    // If no partecipant in headers skip automatic token creation
                    if (!isset($aParticipant)) return;
                    $sEmail = $aParticipant["email"] ?? '';
                    // If no partecipant email is supplied skip automatic token creation
                    if (!isset($sEmail)) return;
                    $oToken = Token::model($iSurveyId)->findByAttributes(array('email' => $sEmail));
                    if (!isset($oToken))
                    {
                        $oToken = $this->unrestricted_add_participant($iSurveyId, $aParticipant);
                    }
                    $token = $oToken->token;
                    // User not created automaticaly
                    // TODO We could generate, need to verify if $token->generateToken(); is enough
                    if (!isset($token)) return;
                    $params = array_merge( $_GET, array( 'token' => $token));
                    $new_query_string = http_build_query( $params );
                    session_destroy();
                    header("Location:.?" . $new_query_string);
                    exit(0);
                    
                }
            }
        }
    }
    
    private function isInGroups($sGroupSettingName)
    {
        $sGroupHeader = $this->getSettingOrDefault('groupsHeader');
        $UserGroups = $this->groupsFromString($_SERVER[$sGroupHeader] ?? '');
        $adminGroups = $this->groupsFromString($this->getSettingOrDefault($sGroupSettingName));
        foreach ($adminGroups as $sAdminGroup){
            if (in_array($sAdminGroup, $UserGroups)) return true;
        }
        return false;
    }
    
    private function isInAdminGroups()
    {
        return $this->isInGroups('adminGroups');
    }
    
    private function isInServiceDeskGroups()
    {
        return $this->isInGroups('serviceDeskGroups');
    }

    private function isInEditorGroups()
    {
        return $this->isInGroups('editorGroups');
    }


    public function beforeLogin()
    {
        $sUserHeader = $this->getSettingOrDefault('userHeader');
        $sUser = $_SERVER[$sUserHeader] ?? '';        
        if (isset($sUser) && ($this->isInAdminGroups() || $this->isInServiceDeskGroups() || $this->isInEditorGroups())) {
            $this->setUsername($sUser);
            $this->setAuthPlugin(); // This plugin handles authentication, halt further execution of auth plugins
            return;
        }
        $this->setAuthFailure(self::ERROR_AUTH_METHOD_INVALID, gT('Missing headers for user or groups'));
        return;
    }

    private function createUser($aUserProfile)
    {
        $oUser = new User();
        $oUser->users_name = $aUserProfile["email"];
        $oUser->setPassword(createPassword());
        $oUser->full_name = $aUserProfile['full_name'];
        $oUser->parent_id = 1;
        $oUser->lang = $aUserProfile['language'];
        $oUser->email = $aUserProfile['email'];

        if ($oUser->save()) {
            Permission::model()->setGlobalPermission($oUser->uid, 'auth_unibo');
            return $oUser;
        }
        return;
    }

    public function newUserSession()
    {
        // Do nothing if this user is not Authwebserver type
        $identity = $this->getEvent()->get('identity');
        if ($identity->plugin != 'UniboGroupsAuth') {
            return;
        }
        
        $sUsername = $this->getUserName();
        $aUserProfile = $this->getUserProfile();
        // If no profile in headers skip automatic user creation
        if (!isset($aUserProfile)) return;
        $oUser = $this->api->getUserByName($sUsername);
        if (!isset($oUser))
        {
            $oUser = $this->createUser($aUserProfile);
        }
        if (Permission::model()->find('permission = :permission AND uid=:uid AND read_p =1', array(":permission" => 'auth_unibo', ":uid" => $oUser->uid))) {
            if ($this->isInEditorGroups()) $sRoleName = 'editor';
            if ($this->isInServiceDeskGroups()) $sRoleName = 'servicedesk';
            if ($this->isInAdminGroups()) $sRoleName = 'admin';
            if (isset($sRoleName))
            {
                $oRole = Permissiontemplates::model()->findByAttributes(array('name' => $sRoleName));
            }
            if (!isset($oRole)){
                $this->setAuthFailure(self::ERROR_AUTH_METHOD_INVALID, gT('Missing role for this user'));
                return;
            }
            $oRole->clearUser($oUser->uid);
            $oRole->applyToUser($oUser->uid);
            // Superadmin permission does not work on roles 
            $aSuperadminPermissions = self::$aInitialPermissions[$sRoleName];
            foreach ($aSuperadminPermissions["superadmin"] as &$sPermission) {
                $sPermission = $sPermission . "_p";
             }
            Permission::model()->setGlobalPermission($oUser->uid, "superadmin", $aSuperadminPermissions["superadmin"]);
            $this->setAuthSuccess($oUser);
            return;
        }
        else{
            $this->setAuthFailure(self::ERROR_AUTH_METHOD_INVALID, gT('Unibo authentication method is not allowed for this user'));
            return;
        }
    }
}

?>
