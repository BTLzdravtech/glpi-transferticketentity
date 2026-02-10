<?php

namespace GlpiPlugin\Transferticketentity\Controller;

use CommonITILActor;
use CommonITILObject;
use Entity as GlpiEntity;
use Glpi\Controller\AbstractController;
use Glpi\Http\RedirectResponse;
use Group;
use Group_Ticket;
use Html;
use ITILCategory;
use Planning;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Ticket as GlpiTicket;
use Ticket_User;
use TicketTask;
use TicketTemplateMandatoryField;

final class TicketController extends AbstractController
{
    #[Route("/ajax/get_entities_rights.php", name: "transferticketentity_entity_rights")]
    public function getEntity(Request $request): Response
    {
        Session::haveRightsOr(GlpiEntity::$rightname, [CREATE, UPDATE, PURGE]);

        $entity_id = $_GET['entity_id'];
        $response = [];
        if ($entity_id != '-1') {
            $response['rights'] = self::getEntityRights($entity_id);
            $response['groups'] = self::getEntityGroups($entity_id);
        }

        return new JsonResponse($response);
    }

    private static function getEntityRights($entity_id): array
    {
        global $DB;

        return $DB->request([
            'SELECT' => ['entities_id', 'allow_entity_only_transfer', 'justification_transfer', 'allow_transfer', 'keep_category', 'itilcategories_id'],
            'FROM' => 'glpi_plugin_transferticketentity_entities_settings',
            'WHERE' => ['entities_id' => $entity_id],
        ])->current();
    }

    private static function getEntityGroups($entity_id): array
    {
        global $DB;

        $criteria = [
            'SELECT' => ['id', 'name'],
            'FROM' => Group::getTable(),
        ];
        $criteria = array_merge_recursive($criteria, getEntitiesRestrictCriteria(Group::getTable(), '', $entity_id, true));

        return iterator_to_array($DB->request($criteria), false);
    }

    #[Route("front/ticket.form.php", name: "transferticketentity_ticket")]
    public function transferTicket(Request $request): Response
    {
        $ticket = new GlpiTicket();
        $ticket->getFromDB($_POST["ticket_id"]);

        $entity = new GlpiEntity();
        $entity->getFromDB($_POST["entity_choice"]);

        $group = null;
        if ($_POST["group_choice"] > 0) {
            $group = new Group();
            $group->getFromDB($_POST["group_choice"]);
        }

        $entityRights = self::getEntityRights($_POST["entity_choice"]);

        if (!Session::haveRight('plugin_transferticketentity_bypass', ALLSTANDARDRIGHT) && !$ticket->canTakeIntoAccount()) {
            Session::addMessageAfterRedirect(
                __(
                    "You must be assigned to the ticket to be able to transfer it",
                    'transferticketentity'
                ),
                true,
                ERROR
            );

            return new RedirectResponse(Html::getBackUrl());
        } else {
            $ticketUpdate = [
                'id' => $ticket->getId(),
                'entities_id' => $entity->getId(),
                'status' => $group ? CommonITILObject::ASSIGNED : CommonITILObject::INCOMING,
            ];

            $ticketCategory = $ticket->fields['itilcategories_id'];
            if ($entityRights['keep_category']) {
                if (!self::checkExistingCategory($ticket, $entity)) {
                    $ticketCategory = 0;
                    $ticketUpdate['itilcategories_id'] = $ticketCategory;
                }
            } else {
                if (!$entityRights['itilcategories_id']) {
                    $ticketCategory = 0;
                } else {
                    $ticketCategory = $entityRights['itilcategories_id'];
                }
                $ticketUpdate['itilcategories_id'] = $ticketCategory;
            }

            // If category is mandatory with GLPIs template and category will be null
            if ($ticketCategory == 0 && self::checkMandatoryCategory($entity)) {
                Session::addMessageAfterRedirect(
                    __(
                        "Category will be set to null but its configured as mandatory in GLPIs template, please contact your administrator.",
                        'transferticketentity'
                    ),
                    true,
                    ERROR
                );

                return new RedirectResponse(Html::getBackUrl());
            }

            // Remove the link with the current user
            $deleteLinkUser = [
                'tickets_id' => $ticket->getId(),
                'type' => CommonITILActor::ASSIGN,
            ];

            $ticketUser = new Ticket_User();
            foreach (array_keys($ticketUser->find($deleteLinkUser)) as $id) {
                //delete user
                $ticketUser->delete(['id' => $id]);
            }

            // Remove the link with the current group
            $deleteLinkGroup = [
                'tickets_id' => $ticket->getId(),
                'type' => CommonITILActor::ASSIGN,
            ];

            $groupTicket = new Group_Ticket();
            foreach (array_keys($groupTicket->find($deleteLinkGroup)) as $id) {
                //delete group
                $groupTicket->delete(['id' => $id]);
            }

            $ticket->update($ticketUpdate);

            if ($group) {
                // Change group ticket
                $groupCheck = [
                    'tickets_id' => $ticket->getId(),
                    'groups_id' => $group->getId(),
                    'type' => CommonITILActor::ASSIGN,
                ];

                if (!$groupTicket->find($groupCheck)) {
                    $groupTicket->add($groupCheck);
                } else {
                    $groupTicket->update($groupCheck);
                }
            }

            $justificationText = '';
            if ($_POST['justification'] != '') {
                $justificationText = "<br> <br>" . $_POST['justification'];
            }
            $groupText = '';
            if ($group) {
                $groupText = __(" in the group ", "transferticketentity") . $group->getName();
            }

            // Log the transfer in a task
            $task = new TicketTask();
            $task->add([
                'tickets_id' => $ticket->getId(),
                'is_private' => true,
                'state'      => Planning::INFO,
                'content'    => __(
                    "Escalation to",
                    "transferticketentity"
                ) . " " . $entity->getName() . $groupText . $justificationText,
            ]);

            Session::addMessageAfterRedirect(
                __(
                    "Successful transfer for ticket n° : ",
                    "transferticketentity"
                ) . $ticket->getId(),
                true,
                INFO
            );
            return new RedirectResponse(
                sprintf(
                    '%s/front/ticket.php',
                    $request->getBasePath()
                )
            );
        }
    }

    /**
     * Check if category exist in target entity
     *
     * @param $ticket
     * @param $entity
     * @return bool
     */
    public function checkExistingCategory($ticket, $entity): bool
    {
        global $DB;

        $criteria = [
            'SELECT' => ['id'],
            'FROM' => ITILCategory::getTable(),
        ];
        $criteria = array_merge_recursive($criteria, getEntitiesRestrictCriteria(ITILCategory::getTable(), '', $entity->getId(), true));
        $categoryIdsInTargetEntity = iterator_to_array($DB->request($criteria), false);

        return in_array($ticket->fields['itilcategories_id'], $categoryIdsInTargetEntity);
    }

    /**
     * Check GLPIs mandatory fields
     *
     * @param $entity
     * @return boolean
     */
    public function checkMandatoryCategory($entity): bool
    {
        $mandatoryFields = (new TicketTemplateMandatoryField())->getMandatoryFields($entity->fields['$tickettemplates_id']);

        // Check if category field is mandatory
        return array_key_exists('itilcategories_id', $mandatoryFields);
    }
}
