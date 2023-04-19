<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\ActiveServer;

use Aurora\Api;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $aRequireModules = array(
        'Licensing'
    );

    public function init()
    {
        $this->subscribeEvent('Login::after', array($this, 'onAfterLogin'), 10);
        $this->subscribeEvent('Core::CreateUser::after', array($this, 'onAfterCreateUser'), 10);
        $this->subscribeEvent('Autodiscover::GetAutodiscover::after', array($this, 'onAfterGetAutodiscover'));
        $this->subscribeEvent('Licensing::UpdateSettings::after', array($this, 'onAfterUpdateLicensingSettings'));
    }

    /**
     *
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     *
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    protected function getFreeUsersSlots()
    {
        $mResult = 0;

        /** @var \Aurora\Modules\Licensing\Module */
        $oLicensing = \Aurora\System\Api::GetModule('Licensing');
        if ($oLicensing->IsTrial('ActiveServer') || $oLicensing->IsUnlim('ActiveServer')) {
            $mResult = 1;
        } else {
            $iLicensedUsersCount = (int) $oLicensing->GetUsersCount('ActiveServer');
            $iUsersCount = $this->GetUsersCount();
            $mResult = $iLicensedUsersCount - $iUsersCount;
        }
        return $mResult;
    }

    public function onAfterLogin(&$aArgs, &$mResult)
    {
        $sAgent = $this->oHttp->GetHeader('X-User-Agent');
        if ($sAgent === 'Afterlogic ActiveServer') {
            $oUser = \Aurora\System\Api::getAuthenticatedUser();

            /** @var \Aurora\Modules\Licensing\Module */
            $oLicensing = \Aurora\System\Api::GetModule('Licensing');

            if (!$oLicensing->ValidatePeriod('ActiveServer')) {
                $mResult = false;
                Api::Log('Auth error: ActiveServer is invalid');
            } elseif ($this->getFreeUsersSlots() < 0) {
                $mResult = false;
                Api::Log('Auth error: User limit exceeded, ActiveServer is disabled');
            } elseif (!($oUser && $oUser->{self::GetName() . '::Enabled'})) {
                $mResult = false;
                Api::Log('Auth error: ActiveServer is not enabled for the user');
            }
        }
    }

    public function onAfterCreateUser(&$aArgs, &$mResult)
    {
        $iUserId = isset($mResult) && (int) $mResult > 0 ? (int) $mResult : 0;
        if ($iUserId > 0) {
            $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($iUserId);
            $oSettings = $this->getModuleSettings();

            if ($oUser) {
                if ($this->getFreeUsersSlots() < 1) {
                    if ($oUser->{self::GetName() . '::Enabled'}) {
                        $oUser->setExtendedProp(self::GetName() . '::Enabled', false);
                        \Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser);
                    }
                } elseif ($oUser->{self::GetName() . '::Enabled'} !== $oSettings->EnableForNewUsers) {
                    $oUser->setExtendedProp(self::GetName() . '::Enabled', $oSettings->EnableForNewUsers);
                    \Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser);
                }
            }
        }
    }

    public function onAfterGetAutodiscover(&$aArgs, &$mResult)
    {
        $sEmail = $aArgs['Email'];
        $oSettings = $this->getModuleSettings();

        $sResult = \implode("\n", array(
'		<Culture>en:us</Culture>',
'        <User>',
'            <DisplayName>'.$sEmail.'</DisplayName>',
'            <EMailAddress>'.$sEmail.'</EMailAddress>',
'        </User>',
'        <Action>',
'            <Settings>',
'                <Server>',
'                    <Type>MobileSync</Type>',
'                    <Url>https://'.$oSettings->Server.'/Microsoft-Server-ActiveSync</Url>',
'                    <Name>https://'.$oSettings->Server.'/Microsoft-Server-ActiveSync</Name>',
'                </Server>',
'            </Settings>',
'        </Action>'
        ));

        $mResult = $mResult . $sResult;
    }

    public function onAfterUpdateLicensingSettings(&$aArgs, &$mResult, &$mSubscriptionsResult)
    {
        if ($this->getFreeUsersSlots() < 0) {
            $mSubscriptionsResult = [
                'Result' => false,
                'ErrorCode' => 1,
                'ErrorMessage' => 'User limit exceeded, ActiveServer is disabled.'
            ];
        }
    }

    public function GetEnableModuleForCurrentUser()
    {
        $bResult = false;
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        $iUserId = \Aurora\System\Api::getAuthenticatedUserId();
        if ($iUserId) {
            $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($iUserId);
            if ($oUser) {
                $bResult = $oUser->{self::GetName() . '::Enabled'};
            }
        }

        return $bResult;
    }


    public function GetPerUserSettings($UserId)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($UserId);
        if ($oUser) {
            return array(
                'EnableModule' => $oUser->{self::GetName() . '::Enabled'}
            );
        }

        return null;
    }

    public function UpdatePerUserSettings($UserId, $EnableModule)
    {
        $bResult = false;
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($UserId);

        /** @var \Aurora\Modules\Licensing\Module */
        $oLicensing = \Aurora\System\Api::GetModule('Licensing');
        $iLicensedUsersCount = (int) $oLicensing->GetUsersCount('ActiveServer');
        $iUsersCount = $this->GetUsersCount();
        if (!$oLicensing->IsTrial('ActiveServer') && !$oLicensing->IsUnlim('ActiveServer') && $iUsersCount >= $iLicensedUsersCount && $EnableModule && !$oUser->{self::GetName() . '::Enabled'}) {
            throw new Exceptions\UserLimitExceeded(1, null, 'ActiveSync user limit exceeded.');
        }

        if ($oUser) {
            $oUser->setExtendedProp(self::GetName() . '::Enabled', $EnableModule);
            $bResult = \Aurora\Modules\Core\Module::Decorator()->UpdateUserObject($oUser);
        }

        return $bResult;
    }

    protected function GetUsersCount()
    {
        return \Aurora\Modules\Core\Models\User::where('Properties->'.self::GetName() . '::Enabled', true)->count();
    }

    public function GetSettings()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::NormalUser);

        /** @var \Aurora\Modules\Licensing\Module */
        $oLicensing = \Aurora\System\Api::GetModule('Licensing');
        $oSettings = $this->getModuleSettings();

        $bEnableModuleForUser = false;

        $iUserId = \Aurora\System\Api::getAuthenticatedUserId();
        if ($iUserId) {
            $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserWithoutRoleCheck($iUserId);
            if ($oUser) {
                $bEnableModuleForUser = $oUser->{self::GetName() . '::Enabled'};
            }
        }

        $iFreeSlots = $this->getFreeUsersSlots();
        if ($iFreeSlots < 0) {
            $iFreeSlots = 'User limit exceeded, ActiveSync is disabled';
        }
        $mLicensedUsersCount = $oLicensing->IsTrial('ActiveServer') ||  $oLicensing->IsUnlim('ActiveServer') ? 'Unlim' : $oLicensing->GetUsersCount('ActiveServer');
        $mUsersFreeSlots = $oLicensing->IsTrial('ActiveServer') ||  $oLicensing->IsUnlim('ActiveServer') ? 'Unlim' : $iFreeSlots;

        return array(
            'EnableModule' => !$oSettings->Disabled,
            'EnableModuleForUser' => $bEnableModuleForUser,
            'EnableForNewUsers' => $oSettings->EnableForNewUsers,
            'UsersCount' => $this->GetUsersCount(),
            'LicensedUsersCount' => (int) $mLicensedUsersCount,
            'UsersFreeSlots' => $mUsersFreeSlots,
            'Server' => $oSettings->Server,
            'LinkToManual' => $oSettings->LinkToManual
        );
    }

    public function UpdateSettings($EnableModule, $EnableForNewUsers, $Server, $LinkToManual)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);

        $bResult = false;

        try {
            $this->setConfig('Disabled', !$EnableModule);
            $this->setConfig('EnableForNewUsers', $EnableForNewUsers);
            $this->setConfig('Server', $Server);
            $this->setConfig('LinkToManual', $LinkToManual);
            $bResult = $this->saveModuleConfig();
        } catch (\Exception $ex) {
            throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Notifications::CanNotSaveSettings);
        }

        return $bResult;
    }

    public function GetLicenseInfo()
    {
        $mResult = false;

        /** @var \Aurora\Modules\Licensing\Module */
        $oLicensing = \Aurora\System\Api::GetModule('Licensing');
        if ($oLicensing) {
            $mResult = $oLicensing->GetLicenseInfo('ActiveServer');
        }

        return $mResult;
    }
}
