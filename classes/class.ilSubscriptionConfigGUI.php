<?php
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Subscription/vendor/autoload.php');

/**
 * Example configuration user interface class
 *
 * @author  Alex Killing <alex.killing@gmx.de>
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version $Id$
 *
 */
class ilSubscriptionConfigGUI extends ilPluginConfigGUI
{

    const CMD_CANCEL = 'cancel';
    const CMD_CONFIGURE = 'configure';
    const CMD_SAVE = 'save';
    /**
     * @var ilCtrl
     */
    protected $ctrl;
    /**
     * @var ilLanguage
     */
    protected $lng;
    /**
     * @var ilGlobalTemplateInterface
     */
    protected $tpl;

    /** @var ilSubscriptionPlugin */
    protected $pl;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->pl = ilSubscriptionPlugin::getInstance();
    }


    /**
     * @param string $cmd
     */
    public function performCommand($cmd)
    {
        switch ($cmd) {
            case self::CMD_CONFIGURE:
            case self::CMD_SAVE:
            case self::CMD_CANCEL:
                $this->$cmd();
                break;
        }
    }


    public function configure()
    {
        $form = new msConfigFormGUI($this);
        $form->fillForm();
        $this->tpl->setContent($form->getHTML());
    }


    protected function save()
    {
        $form = new msConfigFormGUI($this);
        $form->setValuesByPost();
        if ($form->saveObject()) {
            $this->ctrl->redirect($this, self::CMD_CONFIGURE);
        }
        $this->tpl->setContent($form->getHTML());
    }


    protected function cancel()
    {
        $this->ctrl->redirect($this, self::CMD_CONFIGURE);
    }
}
