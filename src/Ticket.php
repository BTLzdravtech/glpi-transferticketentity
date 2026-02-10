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
use Dropdown;
use Entity as GlpiEntity;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;
use Ticket as GlpiTicket;

class Ticket extends CommonDBTM
{
    public static function getIcon(): string
    {
        return 'ti ti-transfer';
    }

    /**
     * If the profile is authorised, add an extra tab
     *
     * @param CommonGLPI $item Ticket
     * @param int $withtemplate 0
     *
     * @return "Entity ticket transfer"
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiTicket
            && $item->getField('id')) {
            return self::createTabEntry(__s("Transfer Ticket Entity", "transferticketentity"));
        }

        return '';
    }

    /**
     * If we are on tickets, an additional tab is displayed
     *
     * @param CommonGLPI $item Ticket
     * @param int $tabnum 1
     * @param int $withtemplate 0
     *
     * @return true
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof GlpiTicket && Session::haveRight('plugin_transferticketentity_use', ALLSTANDARDRIGHT)) {
            self::showFormTicket($item->getID());
        }

        return true;
    }

    /**
     * Get all the entities which aren't the current entity with their rights
     *
     * @param $ticket
     * @return array
     */
    public static function getEntitiesRights($ticket): array
    {
        global $DB;

        $result = $DB->request([
            'SELECT' => ['E.id', 'E.entities_id', 'E.name', 'TES.allow_entity_only_transfer', 'TES.justification_transfer', 'TES.allow_transfer'],
            'FROM' => 'glpi_entities AS E',
            'LEFT JOIN' => ['glpi_plugin_transferticketentity_entities_settings AS TES' => ['FKEY' => ['E' => 'id', 'TES' => 'entities_id']]],
            'WHERE' => ['NOT' => ['E.id' => $ticket->getEntityID()]],
            'GROUPBY' => 'E.id',
            'ORDER' => 'E.entities_id ASC',
        ]);

        return iterator_to_array($result, false);
    }

    /**
     * Return parent's entity name
     *
     * @param $parent_id
     * @return string
     */
    public static function searchParentEntityName($parent_id): string
    {
        $parent_entity = new GlpiEntity();
        $parent_entity->getFromDB($parent_id);

        return $parent_entity->fields['completename'];
    }

    public static function addStyleSheetAndScript()
    {
        echo Html::css("/plugins/transferticketentity/css/style.css");
        echo Html::script("/plugins/transferticketentity/js/script.js");
    }

    /**
     * Display the ticket transfer form
     *
     * @param $ticket_id
     * @return void
     */
    public static function showFormTicket($ticket_id): void
    {
        $ticket = new GlpiTicket();
        $ticket->getFromDB($ticket_id);

        $entitiesRights = self::getEntitiesRights($ticket);

        if (!Session::haveRight('ticket', UPDATE)) {
            echo Html::scriptBlock(
                "glpi_toast_error('" . __("You don't have right to update tickets. Please contact your administrator.", "transferticketentity") . "')"
            );
            return;
        }
        if (!array_any($entitiesRights, fn($entityRight) => $entityRight['allow_transfer'] == 1)) {
            echo Html::scriptBlock(
                "glpi_toast_error('" . __("No entity available found, transfer impossible.", "transferticketentity") . "')"
            );
            return;
        }

        // Check if ticket is closed
        if ($ticket->isClosed()) {
            echo Html::scriptBlock(
                "glpi_toast_error('" . __("Unauthorized transfer on closed ticket.", "transferticketentity") . "')"
            );
            return;
        }

        $entityDropdownValues = ['-1' => Dropdown::EMPTY_VALUE];
        foreach ($entitiesRights as $entityRight) {
            if ($entityRight['allow_transfer']) {
                if ($entityRight['entities_id'] === null) {
                    $entityDropdownValues[__('No previous entity', 'transferticketentity')][$entityRight['id']] = $entityRight['name'];
                } else {
                    $searchParentEntityName = self::searchParentEntityName($entityRight['entities_id']);
                    $entityDropdownValues[$searchParentEntityName][$entityRight['id']] = $entityRight['name'];
                }
            }
        }

        TemplateRenderer::getInstance()->display('@transferticketentity/ticket.html.twig', [
            'item' => $ticket,
            'form_path' => self::getFormURL(),
            'entityDropdownValues' => $entityDropdownValues,
            'canedit' => true,
        ]);
    }
}
