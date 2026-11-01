<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace BringList {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace BringList {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace BringList {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace BringList {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/AttributeArrayHelper.php') . '}');
eval('declare(strict_types=1);namespace BringList {?>' . file_get_contents(dirname(__DIR__) . '/libs/helper/VariableProfileHelper.php') . '}');
require_once dirname(__DIR__) . '/libs/BringAPI.php';

/**
 * BringList
 *
 * @property int $ParentID
 * @property array $UserDefinedItems
 * @property array $ListUsers
 * @property array $List
 * @method void RegisterParent()
 * @method void RegisterAttributeArray(string $name, mixed $Value, int $Size = 0)
 * @method array ReadAttributeArray(string $name)
 * @method void WriteAttributeArray(string $name, mixed $value)
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void UnregisterProfile(string $Name)
 * @method void RegisterProfileIntegerEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations, int $MaxValue = -1, float $StepSize = 0)
 * @method void RegisterProfileStringEx(string $Name, string $Icon, string $Prefix, string $Suffix, array $Associations)
 */
class BringList extends IPSModuleStrict
{
    use \BringList\DebugHelper;
    use \BringList\BufferHelper;
    use \BringList\AttributeArrayHelper;
    use \BringList\VariableProfileHelper;
    use \BringList\InstanceStatus {
        \BringList\InstanceStatus::MessageSink as IOMessageSink;
        \BringList\InstanceStatus::RequestAction as IORequestAction;
    }
    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->ParentID = 0;
        $this->UserDefinedItems = [];
        //$this->ListUsers = [];
        $this->List = [
            'purchase'=> [],
            'recently'=> []
        ];
        $this->RegisterAttributeArray(\Bring\Attribute::AllLists, []);
        $this->RegisterAttributeString(\Bring\Attribute::ListLocale, '');
        $this->RegisterPropertyString(\Bring\Property::ListUuid, '');
        $this->RegisterPropertyInteger(\Bring\Property::RefreshInterval, 0);
        $this->RegisterPropertyInteger(\Bring\Property::AutomaticallySendNotification, 0);
        $this->RegisterPropertyBoolean(\Bring\Property::EnableNotificationIntegerVariable, true);
        $this->RegisterPropertyBoolean(\Bring\Property::EnableNotificationStringVariable, true);
        $this->RegisterPropertyBoolean(\Bring\Property::EnableRefreshIntegerVariable, true);
        $this->RegisterPropertyBoolean(\Bring\Property::EnableTextboxVariable, true);
        $this->RegisterPropertyBoolean(\Bring\Property::EnableTileDisplay, true);

        $this->RegisterTimer(\Bring\Timer::RefreshList, 0, 'BRING_UpdateList(' . $this->InstanceID . ');');
        $this->RegisterTimer(\Bring\Timer::SendListChangeNotification, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \Bring\Timer::SendListChangeNotification . '",true);');

        $Path = sys_get_temp_dir() . '/SymconBring';
        if (!is_dir($Path)) {
            mkdir($Path);
        }
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\Bring\Timer::RefreshList, 0);
        //Never delete this line!
        parent::ApplyChanges();

        $this->UnregisterProfile('BRING.Reload');
        $this->UnregisterProfile('BRING.Notify');
        if ($this->ReadPropertyBoolean(\Bring\Property::EnableTextboxVariable)) {
            $this->RegisterVariableString(
                \Bring\Variable::TextBox,
                $this->Translate('Purchase'),
                [
                    'MULTILINE'    => true,
                    'PREFIX'       => '',
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'SUFFIX'       => '',
                ],
                1
            );
            $this->EnableAction(\Bring\Variable::TextBox);
        } else {
            $this->UnregisterVariable(\Bring\Variable::TextBox);
        }

        if ($this->ReadPropertyBoolean(\Bring\Property::EnableRefreshIntegerVariable)) {
            $this->RegisterVariableInteger(
                \Bring\Variable::Reload,
                $this->Translate('Reload List'),
                [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'ICON'         => 'arrows-rotate',
                    'OPTIONS'      => json_encode(
                        [
                            [
                                'Value'       => 0,
                                'Caption'     => $this->Translate('Reload'),
                                'Color'       => 0xff0000,
                                'IconActive'  => false,
                                'IconValue'   => ''
                            ]
                        ]
                    )
                ],
                2
            );
            $this->EnableAction(\Bring\Variable::Reload);
        } else {
            $this->UnregisterVariable(\Bring\Variable::Reload);
        }

        if ($this->ReadPropertyBoolean(\Bring\Property::EnableNotificationIntegerVariable)) {
            $this->RegisterVariableString(
                \Bring\Variable::Notify,
                $this->Translate('Send Notification'),
                [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'ICON'         => 'comment-arrow-up-right',
                    'OPTIONS'      => json_encode(
                        [
                            [
                                'Value'       => \Bring\Api\NotificationTypes::SHOPPING_DONE,
                                'Caption'     => $this->Translate('Shopping done'),
                                'Color'       => 0x00ff00,
                                'IconActive'  => false,
                                'IconValue'   => ''
                            ],
                            [
                                'Value'       => \Bring\Api\NotificationTypes::GOING_SHOPPING,
                                'Caption'     => $this->Translate('Go shopping'),
                                'Color'       => 0xff0000,
                                'IconActive'  => false,
                                'IconValue'   => ''
                            ],
                            [
                                'Value'       => \Bring\Api\NotificationTypes::CHANGED_LIST,
                                'Caption'     => $this->Translate('List changed'),
                                'Color'       => 0x0000ff,
                                'IconActive'  => false,
                                'IconValue'   => ''
                            ]
                        ]
                    )
                ],
                3
            );
            $this->EnableAction(\Bring\Variable::Notify);
        } else {
            $this->UnregisterVariable(\Bring\Variable::Notify);
        }

        if ($this->ReadPropertyBoolean(\Bring\Property::EnableNotificationStringVariable)) {
            $this->RegisterVariableString(
                \Bring\Variable::UrgentItem,
                $this->Translate('Send Urgent Item Notification'),
                [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT
                ],
                4
            );
            $this->EnableAction(\Bring\Variable::UrgentItem);
        } else {
            $this->UnregisterVariable(\Bring\Variable::UrgentItem);
        }

        $this->SetVisualizationType((int) $this->ReadPropertyBoolean(\Bring\Property::EnableTileDisplay));

        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->RegisterParent();

        if ($this->HasActiveParent()) {
            $AllLists = @$this->GetLists();
            $this->WriteAttributeArray(\Bring\Attribute::AllLists, $AllLists);
            $Values = [];
            foreach ($AllLists as $List) {
                $Values[] = [
                    'caption'=> $List[\Bring\Attribute::Name],
                    'value'  => $List[\Bring\Property::ListUuid]
                ];
            }
            $this->UpdateFormField(\Bring\Property::ListUuid, 'options', json_encode($Values));
            $this->UserDefinedItems = @$this->GetListDetail();
            $this->List = [
                'purchase'=> [],
                'recently'=> []
            ];
            if ($this->ReadPropertyString(\Bring\Property::ListUuid)) {
                @$this->LoadLocal();
                @$this->UpdateList();
                $this->SetTimerInterval(\Bring\Timer::RefreshList, $this->ReadPropertyInteger(\Bring\Property::RefreshInterval) * 1000);
            }

        }
    }

    /**
     * MessageSink
     * Interne Funktion des SDK.
     *
     * @param  int $TimeStamp
     * @param  int $SenderID
     * @param  int $Message
     * @param  array $Data
     * @return void
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    /**
     * RequestAction
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return void
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            case \Bring\Variable::TextBox:
                $ListChanged = false;
                $OldItems = preg_split('/\r\n|\r|\n/', $this->GetValue(\Bring\Variable::TextBox));
                $NewItems = preg_split('/\r\n|\r|\n/', $Value);
                $DeleteItems = [];
                $AddItems = [];
                foreach (array_diff($OldItems, $NewItems) as $Item) {
                    $DeleteItems[] = self::DecodeItemString(trim($Item));
                }
                foreach (array_diff($NewItems, $OldItems) as $Item) {
                    $AddItems[] = self::DecodeItemString(trim($Item));
                }
                $Add = array_diff_key($AddItems, array_intersect(array_column($AddItems, 'itemId'), array_column($DeleteItems, 'itemId')));
                $Delete = array_diff_key($DeleteItems, array_intersect(array_column($DeleteItems, 'itemId'), array_column($AddItems, 'itemId')));
                $Change = array_intersect_key($AddItems, array_intersect(array_column($AddItems, 'itemId'), array_column($DeleteItems, 'itemId')));
                $this->SendDebug('Add', $Add, 0);
                $this->SendDebug('Delete', $Delete, 0);
                $this->SendDebug('Change', $Change, 0);
                foreach ($Delete as $DeleteItem) {
                    //$DeleteItem['operation'] = \Bring\Api\BringItemOperation::COMPLETE;
                    if (!$this->AddToRecentlyItem($DeleteItem['itemId'], $DeleteItem['specification'])) {
                        set_error_handler([$this, 'ModulErrorHandler']);
                        trigger_error($this->Translate('Error on change the list'), E_USER_WARNING);
                        restore_error_handler();
                    }
                    $ListChanged = true;
                }
                foreach ($Add as $AddItem) {
                    //$AddItem['operation'] = \Bring\Api\BringItemOperation::ADD;
                    if (!$this->AddItem($AddItem['itemId'], $AddItem['specification'])) {
                        set_error_handler([$this, 'ModulErrorHandler']);
                        trigger_error($this->Translate('Error on change the list'), E_USER_WARNING);
                        restore_error_handler();
                    }
                    $ListChanged = true;
                }
                foreach ($Change as $ChangeItem) {
                    if (!$this->AddItem($ChangeItem['itemId'], $ChangeItem['specification'])) {
                        set_error_handler([$this, 'ModulErrorHandler']);
                        trigger_error($this->Translate('Error on change the list'), E_USER_WARNING);
                        restore_error_handler();
                    }
                    $ListChanged = true;
                }
                if ($ListChanged) {
                    $this->UpdateList();
                    $this->SetTimerInterval(\Bring\Timer::SendListChangeNotification, $this->ReadPropertyInteger(\Bring\Property::AutomaticallySendNotification) * 1000);
                }
                break;
            case \Bring\Variable::Reload:
                $this->UpdateList();
                break;
            case \Bring\Variable::Notify:
                $this->SendNotify($Value);
                break;
            case \Bring\Variable::UrgentItem:
                $this->SendUrgentItemNotify($Value);
                break;
            case \Bring\Timer::SendListChangeNotification:
                $this->SetTimerInterval(\Bring\Timer::SendListChangeNotification, 0);
                $this->SendNotify(\Bring\Api\NotificationTypes::CHANGED_LIST);
                break;
            case 'purchased':
                $removeItem = json_decode($Value, true);
                $Items = $this->List;
                $Messages = [];
                foreach ($Items['purchase'] as $Index => $Item) {
                    if ($Item['name'] == $removeItem['name']) {
                        if ($this->AddToRecentlyItem($removeItem['name'], $removeItem['specification'])) {
                            $this->AddVisuUpdateMessage($Messages, 'recently', $Item, '');
                            $this->UpdateVisualizationValue(json_encode($Messages));
                            $Items['recently'][] = $Item;
                            unset($Items['purchase'][$Index]);
                            $this->List = $Items;
                            $this->UpdateTextboxVariable();
                            $this->SetTimerInterval(\Bring\Timer::SendListChangeNotification, $this->ReadPropertyInteger(\Bring\Property::AutomaticallySendNotification) * 1000);
                        }
                        return;
                    }
                }
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Error on change the list'), E_USER_WARNING);
                restore_error_handler();
                break;
            case 'addItem':
                $this->SendDebug('Action: ' . $Ident, $Value, 0);
                $addItem = json_decode($Value, true);
                $addItem['name'] = ucfirst($addItem['name']);
                $Items = $this->List;
                $Messages = [];
                $FoundIndex = false;
                $Icon = '';
                if ($this->AddItem($addItem['name'], $addItem['specification'])) {
                    foreach ($Items['recently'] as $Index => $Item) {
                        if ($Item['name'] == $addItem['name']) {
                            $addItem = array_merge($Item, $addItem);
                            $FoundIndex = $Index;
                            break;
                        }
                    }

                    if ($FoundIndex) {
                        unset($Items['recently'][$Index]);
                    } else {
                        $this->SendDebug('GetIcon: ' . $Ident, $addItem['name'], 0);
                        $Icon = $this->GetIcon($addItem['name']);
                    }
                    $this->AddVisuUpdateMessage($Messages, 'purchase', $addItem, $Icon);
                    $this->UpdateVisualizationValue(json_encode($Messages));
                    $this->SendDebug('addItem', $addItem, 0);
                    $Items['purchase'][] = $addItem;
                    $this->List = $Items;
                    $this->UpdateTextboxVariable();
                    $this->SetTimerInterval(\Bring\Timer::SendListChangeNotification, $this->ReadPropertyInteger(\Bring\Property::AutomaticallySendNotification) * 1000);
                    return;
                }
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Error on change the list'), E_USER_WARNING);
                restore_error_handler();
                break;
            case 'editSpecification':
                $this->SendDebug('Action: ' . $Ident, $Value, 0);
                $editItem = json_decode($Value, true);
                $this->SendDebug('editItem', $editItem, 0);
                $Items = $this->List;
                foreach ($Items['purchase'] as &$Item) {
                    if ($Item['name'] == $editItem['name']) {
                        if ($this->AddItem($editItem['name'], $editItem['specification'])) {
                            $Item['specification'] = $editItem['specification'];
                            $this->List = $Items;
                            $this->UpdateTextboxVariable();
                            $this->SetTimerInterval(\Bring\Timer::SendListChangeNotification, $this->ReadPropertyInteger(\Bring\Property::AutomaticallySendNotification) * 1000);
                        }
                        return;
                    }
                }
                set_error_handler([$this, 'ModulErrorHandler']);
                trigger_error($this->Translate('Error on change the list'), E_USER_WARNING);
                restore_error_handler();
                break;
            default:
                $this->SendDebug('Action: ' . $Ident, $Value, 0);
                break;
        }
    }

    public function GetVisualizationTile(): string
    {
        $Messages = [];
        // Get current values and push to message array
        $List = $this->List;
        foreach ($List['purchase'] as $Item) {
            $this->AddVisuUpdateMessage($Messages, 'purchase', $Item, $this->GetIcon($Item['name']));
        }
        foreach ($List['recently'] as $Item) {
            $this->AddVisuUpdateMessage($Messages, 'recently', $Item, $this->GetIcon($Item['name']));
        }
        $this->SendDebug(__FUNCTION__, $List, 0);

        // Add static HTML content from file
        $module = file_get_contents(__DIR__ . '/module.html');

        // Add article database
        $ListLocale = $this->ReadAttributeString(\Bring\Attribute::ListLocale);
        $LocalCatalog = [];
        if ($ListLocale) {
            $Path = sys_get_temp_dir() . '/SymconBring';
            $Filename = 'article.' . $ListLocale . '.json';
            $File = $Path . '/' . $Filename;
            if (file_exists($File)) {
                $LocalCatalog = json_decode(file_get_contents($File), true);
            }
        }
        $UserDefinedItems = $this->UserDefinedItems;
        if (count($UserDefinedItems)) {
            $UserDefinedItemIdColumn = array_column($UserDefinedItems, 'itemId');
            $LocalCatalog = array_merge($LocalCatalog, array_combine($UserDefinedItemIdColumn, $UserDefinedItemIdColumn));
        }
        $module = str_replace('const localArticles = {}', 'const localArticles = ' . json_encode($LocalCatalog), $module);

        // Inject current values using the message handling function
        $all = $module . '<script>' . 'handleMessage(\'' . json_encode($Messages) . '\');' . '</script>';
        $this->SendDebug('TileSize Bytes', strlen($all), 0);
        return $all;
    }

    /**
     * UpdateList
     *
     * @return bool
     */
    public function UpdateList(): bool
    {
        $Result = $this->GetList();
        if (!$Result) {
            return false;
        }
        $this->List = $Result;
        $this->SendDebug('LIST', $Result, 0);

        $this->UpdateTextboxVariable();

        if ($this->ReadPropertyBoolean(\Bring\Property::EnableTileDisplay)) {
            $Messages = [];
            foreach ($Result['purchase'] as $Item) {
                $this->AddVisuUpdateMessage($Messages, 'purchase', $Item, $this->GetIcon($Item['name']));
            }
            foreach ($Result['recently'] as $Item) {
                $this->AddVisuUpdateMessage($Messages, 'recently', $Item, $this->GetIcon($Item['name']));
            }
            $this->SendDebug('Messages', $Messages, 0);
            $this->UpdateVisualizationValue(json_encode($Messages));
        }
        return true;
    }

    /**
     * GetList
     *
     * Lädt die Liste neu, und gibt sie alls Array zurück.
     * Public, damit eigene Umsetzungen z.B. mit HTML-Boxen möglich sind.
     *
     * @return array|false
     */
    public function GetList(): array|false
    {
        $JSON = \Bring\Api::GetList($this->ReadPropertyString(\Bring\Property::ListUuid));
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return $Result;
    }

    /**
     * SendNotify
     *
     * @param  string $NotificationType
     * @return bool
     */
    public function SendNotify(string $NotificationType): bool
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $JSON = \Bring\Api::SendNotification(
            $this->ReadPropertyString(\Bring\Property::ListUuid),
            $NotificationType
        );
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return $Result;
    }

    /**
     * SendUrgentItemNotify
     *
     * @param  string $NotificationType
     * @return bool
     */
    public function SendUrgentItemNotify(string $ItemName): bool
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $JSON = \Bring\Api::SendNotification(
            $this->ReadPropertyString(\Bring\Property::ListUuid),
            \Bring\Api\NotificationTypes::URGENT_MESSAGE,
            $ItemName
        );
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return $Result;
    }

    /**
     * AddItem
     *
     * @param  string $ItemName
     * @param  string $Specification
     * @return bool
     */
    public function AddItem(string $ItemName, string $Specification): bool
    {
        $this->SendDebug(__FUNCTION__, $ItemName, 0);
        $JSON = \Bring\Api::AddItem(
            $this->ReadPropertyString(\Bring\Property::ListUuid),
            $ItemName,
            $Specification
        );
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return true;
    }

    /**
     * AddToRecentlyItem
     *
     * @param  string $ItemName
     * @param  string $Specification
     * @return bool
     */
    public function AddToRecentlyItem(string $ItemName, string $Specification): bool
    {
        $this->SendDebug(__FUNCTION__, $ItemName, 0);
        $JSON = \Bring\Api::AddToRecentlyItem(
            $this->ReadPropertyString(\Bring\Property::ListUuid),
            $ItemName,
            $Specification
        );
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return true;
    }

    /**
     * RemoveItem
     *
     * @param  string $ItemName
     * @return bool
     */
    /*
    public function RemoveItem(string $ItemName): bool
    {
        $JSON = \Bring\Api::RemoveItem(
            $this->ReadPropertyString(\Bring\Property::ListUuid),
            $ItemName
        );
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return true;
    }
     */

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Lists = $this->ReadAttributeArray(\Bring\Attribute::AllLists);
        $Values = [];
        foreach ($Lists as $List) {
            $Values[] = [
                'caption'=> $List[\Bring\Attribute::Name],
                'value'  => $List[\Bring\Property::ListUuid]
            ];
        }
        $Form['elements'][0]['items'][0]['options'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * KernelReady
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     *
     * @return void
     */
    protected function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     * @access protected
     */
    protected function IOChangeState(int $State): void
    {
        switch ($State) {
            case IS_ACTIVE:
                $this->ApplyChanges();
                break;
        }
    }

    /**
     * ModulErrorHandler
     *
     * @param  int $errno
     * @param  string $errstr
     * @return true
     */
    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        echo $errstr . PHP_EOL;
        return true;
    }
    /**
     * DecodeItemString
     *
     * @param  string $Line
     * @return array
     */
    protected static function DecodeItemString(string $Line): array
    {
        preg_match_all("/(?J)((?<ItemName>.*)\((?<ItemDesc>.*)\))|(?<ItemName>.*)/", $Line, $Result, PREG_SET_ORDER);
        $Item['itemId'] = trim($Result[0]['ItemName']);
        $Item['specification'] = trim($Result[0]['ItemDesc']);
        return $Item;
    }

    /**
     * GetLists
     *
     * @return array
     */
    private function GetLists(): array
    {
        $Result = $this->SendDataToParent(\Bring\Api::GetLists());
        if (!$Result) {
            return [];
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return [];
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return $Result['lists'];
    }

    /**
     * GetTheme
     *
     * @return string
     */
    /*
    public function GetTheme(): string
    {
        $Lists = $this->ReadAttributeArray(\Bring\Attribute::AllLists);
        if (!count($Lists)) {
            return '';
        }
        $Themes = array_column($Lists, 'theme', \Bring\Property::ListUuid);
        $this->SendDebug(__FUNCTION__, $Themes, 0);
        return $Themes[$this->ReadPropertyString(\Bring\Property::ListUuid)] ?? '';
    }
     */

    /**
     * GetAllUsersFromList
     *
     * @return array
     */
    /*
    public function GetAllUsersFromList(): array
    {
        $Result = $this->SendDataToParent(\Bring\Api::GetAllUsersFromList($this->ReadPropertyString(\Bring\Property::ListUuid)));
        if (!$Result) {
            return [];
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return [];
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return $Result['users'];
    }
     */

    /**
     * GetListDetail
     *
     * @return array
     */
    private function GetListDetail(): array
    {
        $JSON = \Bring\Api::GetListDetail($this->ReadPropertyString(\Bring\Property::ListUuid));
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return [];
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return [];
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        return $Result;
    }

    /**
     * ChangeMultipleItems
     *
     * @param  array $Items
     * @return bool
     */
    private function ChangeMultipleItems(array $Items): bool
    {
        $this->SendDebug(__FUNCTION__, $Items, 0);
        $JSON = \Bring\Api::ChangeMultipleItems($this->ReadPropertyString(\Bring\Property::ListUuid), $Items);
        $this->SendDebug(__FUNCTION__, $JSON, 0);
        $Result = $this->SendDataToParent($JSON);
        $this->SendDebug(__FUNCTION__, $Result, 0);
        if (!$Result) {
            return false;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return false;
        }
        return $Result;
    }

    /**
     * UpdateTextboxVariable
     *
     * @return void
     */
    private function UpdateTextboxVariable(): void
    {
        if (!$this->ReadPropertyBoolean(\Bring\Property::EnableTextboxVariable)) {
            return;
        }

        $List = $this->List;
        $Purchase = [];
        foreach ($List['purchase'] as $Item) {
            $Purchase[] = $Item['name'] . ($Item['specification'] ? ' (' . trim($Item['specification']) . ')' : '');
        }
        $this->SetValue(\Bring\Variable::TextBox, implode("\r\n", $Purchase));
    }

    /**
     * AddVisuUpdateMessage
     *
     * @param  array &$Messages
     * @param  string $Typ
     * @param  array $Item
     * @param  string $Icon
     * @return void
     */
    private static function AddVisuUpdateMessage(array &$Messages, string $Typ, array $Item, string $Icon = ''): void
    {
        $Item['icon'] = $Icon;
        $Messages[] =
          [
              'ident' => $Typ,
              'item'  => $Item
          ];

    }

    /**
     * convertIconName
     *
     * @param  string $Name
     * @return string
     */
    private static function convertIconName(string $Name): string
    {
        return str_replace(['ö', 'ä', 'ü', 'ß'], ['oe', 'ae', 'ue', 'ss'], $Name);
    }

    /**
     * LoadLocal
     *
     * @return void
     */
    private function LoadLocal(): void
    {
        $JSON = \Bring\Api::GetUserSettings();
        $Result = $this->SendDataToParent($JSON);
        if (!$Result) {
            return;
        }
        $Result = unserialize($Result);
        if ($Result === false) {
            return;
        }
        $this->SendDebug(__FUNCTION__, $Result, 0);
        $List = $this->ReadPropertyString(\Bring\Property::ListUuid);
        foreach ($Result['userlistsettings'] as $ListSetting) {
            if ($List == $ListSetting[\Bring\Property::ListUuid]) {
                $UserSetting = $ListSetting['usersettings'];
                $this->SendDebug(__FUNCTION__ . ' UserSetting', $UserSetting, 0);

                $ListLocale = array_column($UserSetting, 'value', 'key')['listArticleLanguage'];
                $this->SendDebug(__FUNCTION__ . ' ListLocal', $ListLocale, 0);
                $Path = sys_get_temp_dir() . '/SymconBring';
                $Filename = 'article.' . $ListLocale . '.json';
                $File = $Path . '/' . $Filename;
                if (($this->ReadAttributeString(\Bring\Attribute::ListLocale) != $ListLocale) || !file_exists($File)) {
                    $Url = sprintf(\Bring\Api::BringLocalesURL, $ListLocale);
                    $this->SendDebug(__FUNCTION__ . ' Url', $Url, 0);
                    $Catalog = @Sys_GetURLContentEx($Url, ['Timeout' => 5000]);
                    if (!$Catalog) {
                        return;
                    }
                    $this->SendDebug(__FUNCTION__, $Catalog, 0);
                    file_put_contents($File, $Catalog);
                    $this->WriteAttributeString(\Bring\Attribute::ListLocale, $ListLocale);
                    return;
                }
            }
        }
    }

    /**
     * TranslateItemNameFromUserLocal
     *
     * @param  string $Name
     * @return string
     */
    private function TranslateItemNameFromUserLocal(string $Name, bool &$Found = false): string
    {
        $ListLocale = $this->ReadAttributeString(\Bring\Attribute::ListLocale);
        if (!$ListLocale) {
            return $Name;
        }

        $Path = sys_get_temp_dir() . '/SymconBring';
        $Filename = 'article.' . $ListLocale . '.json';
        $File = $Path . '/' . $Filename;
        if (!file_exists($File)) {
            return $Name;
        }
        $LocalCatalog = json_decode(file_get_contents($File), true);
        $Index = array_search($Name, $LocalCatalog);
        if ($Index === false) {
            return $Name;
        }
        $Found = true;
        return $Index;
    }

    /**
     * GetIcon
     *
     * @param  string $Name
     * @return string
     */
    private function GetIcon(string $Name): string
    {
        $UserDefinedItems = $this->UserDefinedItems;
        $UserDefinedItemIdColumn = array_column($UserDefinedItems, 'itemId');
        $Index = array_search($Name, $UserDefinedItemIdColumn);
        if ($Index === false) {
            $Name = mb_strtolower($this->TranslateItemNameFromUserLocal($Name, $Index));
        } else {
            $Name = mb_strtolower($UserDefinedItems[$Index]['userIconItemId']);
        }
        $this->SendDebug(__FUNCTION__ . ' Name', $Name, 0);
        $this->SendDebug(__FUNCTION__, $Index, 0);
        $Path = sys_get_temp_dir() . '/SymconBring';
        $Filename = self::convertIconName($Name);
        $File = $Path . '/' . $Filename . '.png';
        if (file_exists($File)) {
            return 'data:image/png;base64,' . base64_encode(file_get_contents($File));
        }
        $Icon = '';
        if ($Index) {
            $Icon = $this->LoadImage('items/' . $Filename);
        }
        if (!$Icon) {
            $Filename = self::convertIconName(mb_substr($Name, 0, 1));
            $this->SendDebug(__FUNCTION__, $Filename, 0);
            $File = $Path . '/' . $Filename . '.png';
            if (file_exists($File)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($File));
            }
            $Icon = $this->LoadImage('items/' . $Filename);
        }
        file_put_contents($File, $Icon);
        return 'data:image/png;base64,' . base64_encode($Icon);
    }

    /**
     * LoadImage
     *
     * @param  string $Uri
     * @return string
     */
    private function LoadImage(string $Uri): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_URL, \Bring\Api::BringImagesURL . $Uri . '.png');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        /** @var array $_IPS */
        $this->SendDebug('RequestURL:' . $_IPS['THREAD'], \Bring\Api::BringImagesURL . $Uri . '.png', 0);
        $response = curl_exec($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, \Bring\Api::GetWebHeader());
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $response = curl_exec($ch);
        $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($HttpCode != 0) {
            $this->SendDebug('Request Headers:' . $_IPS['THREAD'], curl_getinfo($ch)['request_header'], 0);
        }
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        $this->SendDebug('HttpCode:' . $_IPS['THREAD'], $HttpCode, 0);
        $this->SendDebug('curl_errno:' . $_IPS['THREAD'], $curl_errno, 0);
        if ($response) {
            if (strpos($response, '<!') === 0) {
                $response = '';
            }
        }
        $this->SendDebug('Result:' . $_IPS['THREAD'], $response, 0);
        return $response ? $response : '';
    }
}