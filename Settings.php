<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\ActiveServer;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property bool $EnableForNewUsers
 * @property string $Server
 * @property string $LinkToManual
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "EnableForNewUsers" => new SettingsProperty(
                false,
                "bool",
                null,
                "If set to true, adding a new user enables ActiveSync for the account",
            ),
            "Server" => new SettingsProperty(
                "",
                "string",
                null,
                "ActiveServer installation URL",
            ),
            "LinkToManual" => new SettingsProperty(
                "",
                "string",
                null,
                "URL of documentation for configuring ActiveSync by users",
            ),
        ];
    }
}
