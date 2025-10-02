<?php

/**
 * Class ilTokenRegistrationGUI
 *
 * @ilCtrl_isCalledBy ilTokenRegistrationGUI: ilAccountRegistrationGUI
 * @ilCtrl_isCalledBy ilTokenRegistrationGUI: ilRouterGUI, ilUIPluginRouterGUI
 */
class ilTokenRegistrationGUI extends ilAccountRegistrationGUI
{

    /**
     * @var msSubscription
     */
    protected $subscription;
    /**
     * @var ilSubscriptionPlugin
     */
    protected $pl;
    /**
     * @var string
     */
    protected $token;

    public function __construct()
    {
        ilInitialisation::initILIAS();
        global $DIC;

        parent::__construct();
        $this->pl = ilSubscriptionPlugin::getInstance();
        $this->ctrl = $DIC->ctrl();
        $this->token = isset($_GET['token']) ? $_GET['token'] : '';
        $this->subscription = msSubscription::where(array('token' => $this->token))->first();
        $this->ctrl->saveParameter($this, 'token');
    }


    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        switch ($cmd) {
            case 'saveForm':
                $this->{$cmd}();
                break;
            default:
                $this->displayForm();
                break;
        }

        $this->tpl->printToStdout();
    }


    /**
     * @param int $a_usr_id
     */
    public function activateUser($a_usr_id)
    {
        $user = new ilObjUser($a_usr_id);
        $user->setActive(true);
        $user->update();
    }


    /**
     * @param bool $a_force_code
     *
     * @throws \ilException
     */
    protected function __initForm($a_force_code = false)
    {
        // Safety check for subscription during initialization
        if (!$this->subscription || $this->subscription->getDeleted()) {
            throw new ilException('this token is no longer valid');
        }
        
        parent::__initForm();
        /**
         * @var ilPropertyFormGUI $form
         * @var ilTextInputGUI    $usr_email
         */

        $this->form->setFormAction(
            $this->ctrl->getFormAction(
                $this,
                'saveForm'
            )
        );

        $username = $this->form->getItemByPostVar('username');
        if ($username) {
            $username->setValue($this->subscription->getMatchingString());
        }

        $usr_email = $this->form->getItemByPostVar('usr_email');
        $matriculation = $this->form->getItemByPostVar('usr_matriculation');

        switch ($this->subscription->getSubscriptionType()) {
            case msSubscription::TYPE_EMAIL:
                if ($usr_email) {
                    $usr_email->setDisabled(msConfig::getValueByKey('fixed_email'));
                    $usr_email->setValue($this->subscription->getMatchingString());
                    $retype = in_array('setRetypeValue', get_class_methods(get_class($usr_email)));
                    if ($retype) {
                        $usr_email->setRetypeValue($this->subscription->getMatchingString());
                    }
                    if (msConfig::getValueByKey('fixed_email')) {
                        //					$usr_email->setPostVar('usr_email_fixed');
                        $hidden = new ilHiddenInputGUI('usr_email');
                        $hidden->setValue($this->subscription->getMatchingString());
                        $this->form->addItem($hidden);
                        if ($retype) {
                            $hidden_retype = new ilHiddenInputGUI('usr_email_retype');
                            $hidden_retype->setValue($this->subscription->getMatchingString());
                            $this->form->addItem($hidden_retype);
                        }
                    }
                }
                break;
            case msSubscription::TYPE_MATRICULATION:
                if ($matriculation) {
                    $matriculation->setDisabled(msConfig::getValueByKey('fixed_email'));
                    $matriculation->setValue($this->subscription->getMatchingString());
                }
        }
    }


    public function displayForm(): ilGlobalTemplateInterface
    {
        if (!$this->subscription || $this->subscription->getDeleted() == 1) {
            $this->tpl->setContent($this->pl->txt('main_not_invalid_token'));
            return $this->tpl;
        } elseif ($this->subscription->getUserStatus() == msUserStatus::STATUS_USER_CAN_BE_ASSIGNED OR $this->subscription->getUserStatus()
            == msUserStatus::STATUS_ALREADY_ASSIGNED
        ) {
            $this->assignUser();
            $this->redirectToCourse();
            return $this->tpl;
        } else {
            $form_template = parent::displayForm();
            $this->tpl->setContent($form_template->get());
            return $this->tpl;
        }
    }


    public function assignUser()
    {
        $obj_id = ilObject::_lookupObjId($this->subscription->getObjRefId());
        $a_usr_id = $this->subscription->user_status_object->getUsrId();
        switch ($this->subscription->getContext()) {
            case msSubscription::CONTEXT_CRS:
                $participants = new ilCourseParticipants($obj_id);
                $participants->add($a_usr_id, $this->subscription->getRole());
                break;
            case msSubscription::CONTEXT_GRP:
                $participants = new ilGroupParticipants($obj_id);
                $participants->add($a_usr_id, $this->subscription->getRole());
                break;
        }

        $this->activateUser($a_usr_id);
        $this->subscription->setDeleted(true);
        $this->subscription->update();
    }


    public function redirectToCourse()
    {
        header('Location: ' . ilLink::_getStaticLink($this->subscription->getObjRefId()));
    }


    public function saveForm(): ilGlobalTemplateInterface
    {
        // Safety check - ensure we have subscription data
        if (!$this->subscription) {
            throw new ilException('No valid subscription found!');
        }
        
        $matchingString = $this->subscription->getMatchingString();
        switch ($this->subscription->getSubscriptionType()) {
            case msSubscription::TYPE_EMAIL:
                if (!isset($_POST['usr_email']) || !isset($_POST['usr_email_retype']) ||
                    $_POST['usr_email'] != $matchingString ||
                    $_POST['usr_email_retype'] != $matchingString
                ) {
                    throw new ilException('no valid email!');
                }
                break;
            case msSubscription::TYPE_MATRICULATION:
                if (!isset($_POST['usr_matriculation']) || $_POST['usr_matriculation'] != $matchingString) {
                    throw new ilException('no valid matriculation!');
                }
                break;
        }

        if (parent::saveForm()) {
            $this->assignUser();
            $this->redirectToCourse();
        }
        
        return $this->tpl;
    }


    /**
     * @param string $password
     */
    public function loginDeprecated($password)
    {
        if (isset($this->userObj)) {
            $_POST['username'] = $this->userObj->getLogin();
            $_POST['password'] = $password;
            ilInitialisation::initILIAS();
        }
    }


    public function subscribeToAllCourses()
    {
    }
}
