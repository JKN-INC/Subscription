<?php

/**
 * Class subscrTriageGUI
 *
 * @author            Fabian Schmid <fs@studer-raimann.ch>
 * @version           1.0.0
 *
 * @ilCtrl_IsCalledBy subscrTriageGUI : ilRouterGUI, ilUIPluginRouterGUI
 */
class subscrTriageGUI
{

    const CMD_HAS_LOGIN = 'hasLogin';
    const CMD_HAS_NO_LOGIN = 'hasNoLogin';
    const CMD_START = 'start';
    /**
     * @var string
     */
    protected $token;
    /**
     * @var ilSubscriptionPlugin
     */
    protected $pl;
    /**
     * @var msSubscription
     */
    protected $subscription;
    /**
     * @var ilCtrl
     */
    protected $ctrl;
    /**
     * @var \ilGlobalTemplateInterface
     */
    protected $tpl;


    public function __construct()
    {
        global $DIC;
        $this->db = $DIC->database();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->ctrl = $DIC->ctrl();
        $this->pl = ilSubscriptionPlugin::getInstance();

        $this->token = (string) $_REQUEST['token'];
        $this->subscription = msSubscription::getInstanceByToken($this->token);
        $this->ctrl->setParameter($this, 'token', $this->token);
    }


    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd(self::CMD_START);
        if (!$this->subscription instanceof msSubscription) {
            throw new ilException('This token has already been used');
        }
        switch ($cmd) {
            case self::CMD_START:
            case self::CMD_HAS_LOGIN:
            case self::CMD_HAS_NO_LOGIN:
                $this->{$cmd}();
                break;
            default:
                break;
        }

        $this->tpl->printToStdout();
    }


    protected function hasLogin()
    {
        $this->redirectToLogin();
    }


    protected function hasNoLogin()
    {
        $this->determineLogin();
    }


    public function start()
    {
        if (msConfig::getValueByKey('ask_for_login')) {
            if ($this->subscription->getAccountType() == msAccountType::TYPE_SHIBBOLETH) {
                $this->tpl->setOnScreenMessage('info', 'Ihre E-Mailadresse wurde als SwitchAAI-Adresse erkannt. Sie können sich direkt einloggen. Klicken Sie auf Login und wählen Sie Ihre Home-Organisation aus.'); // TODO: Translate
                $this->tpl->setContent('<a href="' . $this->getLoginLonk() . '" class="submit">Login</a>');
            } else {
                $this->showLoginDecision();
            }
        } else {
            $this->determineLogin();
        }

        return;
    }


    protected function showLoginDecision()
    {
        $this->tpl->setTitle($this->pl->txt('triage_title'));

        $de = new ilConfirmationGUI();
        $de->setFormAction($this->ctrl->getFormAction($this));

        $str = $this->subscription->getMatchingString() . ', Ziel: ' // TODO: Translate
            . ilObject2::_lookupTitle(ilObject2::_lookupObjId($this->subscription->getObjRefId()));
        $de->addItem('token', $this->token, $str);

        $de->setHeaderText($this->pl->txt('qst_already_account'));
        $de->setConfirm($this->pl->txt('main_yes'), self::CMD_HAS_LOGIN);
        $de->setCancel($this->pl->txt('main_no'), self::CMD_HAS_NO_LOGIN);

        $this->tpl->setContent($de->getHTML());
    }


    public function determineLogin()
    {
        if (msConfig::checkShibboleth() && $this->subscription->getAccountType() == msAccountType::TYPE_SHIBBOLETH) {
            $this->redirectToLogin();
        } else {
            if (msConfig::getValueByKey('allow_registration')) {
                $this->redirectToTokenRegistrationGUI();
            } else {
                $this->redirectToLogin();
            }
        }

        return;
    }


    public function redirectToLogin()
    {
        $this->setSubscriptionToDeleted();
        $link = $this->getLoginLonk();

        header('Location: ' . $link);
        exit;
    }


    /**
     * @return object
     */
    protected function getRegistrationCode()
    {
        /**
         * @var ilObjCourse $crs
         */
        $crs = ilObjectFactory::getInstanceByRefId($this->subscription->getObjRefId());
        if (!$crs->isRegistrationAccessCodeEnabled()) {
            $crs->enableRegistrationAccessCode(1);
            $crs->update();
        }

        return $crs->getRegistrationAccessCode();
    }


    protected function setSubscriptionToDeleted()
    {
        $this->subscription->setDeleted(true);
        $this->subscription->update();
    }


    protected function redirectToTokenRegistrationGUI()
    {
        $this->ctrl->setParameterByClass(ilTokenRegistrationGUI::class, 'token', $this->token);
        $this->ctrl->redirectByClass(array(ilUIPluginRouterGUI::class, ilTokenRegistrationGUI::class));
    }


    /**
     * @return string
     */
    protected function getLoginLonk()
    {
        return msConfig::getPath() . 'goto.php?target' . $this->subscription->getContextAsString() . '_' . $this->subscription->getObjRefId()
            . '_rcode' . $this->getRegistrationCode();
    }
}
