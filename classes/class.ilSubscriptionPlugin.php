<?php
if (file_exists('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Subscription/vendor/autoload.php')) {
    require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Subscription/vendor/autoload.php');
}

/**
 * Class ilSubscriptionPlugin
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilSubscriptionPlugin extends ilUserInterfaceHookPlugin
{

    const PLUGIN_ID = 'subscription';
    const PLUGIN_NAME = 'Subscription';
    
    protected $DIC;
    /**
     * @var ilSubscriptionPlugin
     */
    protected static $instance;
    /**
     * @var ilDBInterface
     */
    protected ilDBInterface $db;
    /**
     * @var ilRbacReview
     */
    protected $rbacreview;
    /**
     * @var ilComponentRepositoryWrite
     */
    protected ilComponentRepositoryWrite $component_repository;


    /**
     * @return ilSubscriptionPlugin
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     *
     */
    public function __construct()
    {
        global $DIC;
        
        $this->DIC = $DIC;
        $this->db = $DIC->database();
        $this->rbacreview = $DIC['rbacreview'];
        $this->component_repository = $DIC["component.repository"];

        parent::__construct($this->db, $this->component_repository, ilSubscriptionPlugin::PLUGIN_ID);
    }


    /**
     * @return string
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }


    /**
     * @return bool
     */
    protected function beforeUninstall(): bool
    {
        $this->db->dropTable(msConfig::TABLE_NAME, false);
        $this->db->dropTable(msInvitation::TABLE_NAME, false);
        $this->db->dropTable(msSubscription::TABLE_NAME, false);
        $this->db->dropTable(msToken::TABLE_NAME, false);

        return true;
    }
}
