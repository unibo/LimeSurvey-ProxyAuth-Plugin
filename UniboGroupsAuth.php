<?php

class UniboGroupsAuth extends PluginBase {
    protected $storage = 'DbStorage';
    static protected $description = 'Groups authorizzation for Unibo';
    static protected $name = 'UniboGroupsAuth';
    protected $settings = Array(
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
            'help'=>'Will be searched in $_SERVER variable',
        ),
        'groupsHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_GROUPS',
            'label' => 'Key to use for groups',
            'help'=>'Will be searched in $_SERVER variable',
        ),
        'firstNameHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_FIRSTNAME',
            'label' => 'Key to use for first name',
            'help'=>'Will be searched in $_SERVER variable',
        ),
        'lastNameHeader' => array(
            'type' => 'string',
            'default'=>'HTTP_X_REMOTE_LASTNAME',
            'label' => 'Key to use for last name',
            'help'=>'Will be searched in $_SERVER variable',
        ),
    );

    public function init() {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('beforeSurveyPage');
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

    /**
    * Save the settings
    */
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
        $sUser = $_SERVER[$sUserHeader] ?? '';
        $sEmail = $_SERVER[$sEmailHeader] ?? $sUser;
        $sFirstName = $_SERVER[$sFirstNameHeader] ?? '';
        $sLastName = $_SERVER[$sLastNameHeader] ?? '';
        if (empty($sEmail))
        {
            Array();
        }
        return Array
        (
            "email" => $sEmail,
            "firstname" => $sFirstName,
            "lastname" => $sLastName,
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
	print_r($obj);
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
}

?>
