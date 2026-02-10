<?php

/**
 -------------------------------------------------------------------------
 LICENSE

 This file is part of Transferticketentity plugin for GLPI.

 Transferticketentity is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Transferticketentity is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @category  Ticket
 @package   Transferticketentity
 @author    Yannick Comba <y.comba@maine-et-loire.fr>
 @copyright 2015-2023 Département de Maine et Loire plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            https://www.gnu.org/licenses/gpl-3.0.html
 @link      https://github.com/departement-maine-et-loire/
 --------------------------------------------------------------------------
*/

namespace GlpiPlugin\Transferticketentity;

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Plugin;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

class Profile extends CommonDBTM
{
    public static function getIcon(): string
    {
        return 'ti ti-transfer';
    }

    public static function getAllRights()
    {
        return [
            ['itemtype'  => 'PluginTransferTicketEntityUse',
                'label'     => __('Authorized entity transfer', 'transferticketentity'),
                'field'     => 'plugin_transferticketentity_use',
                'rights'    => [ALLSTANDARDRIGHT => __('Active', 'transferticketentity')]],
            ['itemtype'  => 'PluginTransferTicketEntityBypass',
                'label'     => __('Transfer authorized without assignment of technician or associated group', 'transferticketentity'),
                'field'     => 'plugin_transferticketentity_bypass',
                'rights'    => [ALLSTANDARDRIGHT => __('Active', 'transferticketentity')]],
        ];
    }

    /**
     * Add an additional tab
     *
     * @param CommonGLPI $item Ticket
     * @param int $withtemplate 0
     *
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile
            && $item->getField('id')) {
            return self::createTabEntry(__s("Transfer Ticket Entity", "transferticketentity"));
        }

        return '';
    }

    /**
     * If we are on profiles, an additional tab is displayed
     *
     * @param CommonGLPI $item Ticket
     * @param int $tabnum 1
     * @param int $withtemplate 0
     *
     * @return true
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item instanceof GlpiProfile) {
            return self::showForProfile($item->getID());
        }

        return true;
    }

    /**
     * Display the plugin configuration form
     *
     * @param $profiles_id
     * @return true
     */
    public static function showForProfile($profiles_id)
    {
        $profile = new GlpiProfile();
        $profile->getFromDB($profiles_id);

        TemplateRenderer::getInstance()->display('@transferticketentity/profile.html.twig', [
            'item' => $profile,
            'rights' => self::getAllRights(),
        ]);

        return true;
    }
}
