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

final class TicketController extends AbstractController
{
    #[Route("/ajax/get_entities_rights.php", name: "transferticketentity_entity_rights")]
    public function getEntity(Request $request): Response
    {
        Session::checkRight('plugin_transferticketentity_use', ALLSTANDARDRIGHT);

        $entity_id = $request->query->getInt('entity_id', -1);
        $response = [];
        if ($entity_id >= 0) {
            $response['rights'] = self::getEntityRights($entity_id);
            $response['groups'] = $response['rights']['allow_transfer'] ? self::getEntityGroups($entity_id) : [];
        }

        return new JsonResponse($response);
    }

    private static function getEntityRights(int $entity_id): array
    {
        global $DB;

        $rights = $DB->request([
            'SELECT' => ['entities_id', 'allow_entity_only_transfer', 'justification_transfer', 'allow_transfer', 'keep_category', 'itilcategories_id'],
            'FROM' => 'glpi_plugin_transferticketentity_entities_settings',
            'WHERE' => ['entities_id' => $entity_id],
        ])->current();

        return $rights ?? [
            'entities_id' => $entity_id,
            'allow_entity_only_transfer' => 0,
            'justification_transfer' => 0,
            'allow_transfer' => 0,
            'keep_category' => 0,
            'itilcategories_id' => null,
        ];
    }

    private static function getEntityGroups(int $entity_id): array
    {
        global $DB;

        $criteria = [
            'SELECT' => ['id', 'name'],
            'FROM' => Group::getTable(),
        ];
        $criteria = array_merge_recursive($criteria, getEntitiesRestrictCriteria(Group::getTable(), '', $entity_id, true));

        return iterator_to_array($DB->request($criteria), false);
    }

    private static function redirectWithError(string $message): RedirectResponse
    {
        Session::addMessageAfterRedirect($message, true, ERROR);

        return new RedirectResponse(Html::getBackUrl());
    }

    #[Route("/front/ticket.form.php", name: "transferticketentity_ticket")]
    public function transferTicket(Request $request): Response
    {
        Session::checkRight('plugin_transferticketentity_use', ALLSTANDARDRIGHT);

        $ticket = new GlpiTicket();
        $ticket->check($request->request->getInt('ticket_id'), UPDATE);

        if ($ticket->isClosed()) {
            return self::redirectWithError(__("Unauthorized transfer on closed ticket.", "transferticketentity"));
        }

        if (!Session::haveRight('plugin_transferticketentity_bypass', ALLSTANDARDRIGHT) && !$ticket->canTakeIntoAccount()) {
            return self::redirectWithError(__(
                "You must be assigned to the ticket to be able to transfer it",
                'transferticketentity'
            ));
        }

        $entity = new GlpiEntity();
        $entity_id = $request->request->getInt('entity_choice', -1);
        $entityRights = self::getEntityRights($entity_id);
        if (
            $entity_id == $ticket->getEntityID()
            || !$entity->getFromDB($entity_id)
            || !$entityRights['allow_transfer']
        ) {
            return self::redirectWithError(__("No entity available found, transfer impossible.", "transferticketentity"));
        }

        $group = null;
        $group_id = $request->request->getInt('group_choice');
        if ($group_id > 0) {
            if (!in_array($group_id, array_column(self::getEntityGroups($entity_id), 'id'))) {
                return self::redirectWithError(__("No group found with « Assigned to » right while a group is required. Transfer impossible.", "transferticketentity"));
            }
            $group = new Group();
            $group->getFromDB($group_id);
        } elseif ($entityRights['allow_entity_only_transfer']) {
            return self::redirectWithError(__("No group found with « Assigned to » right while a group is required. Transfer impossible.", "transferticketentity"));
        }

        $justification = trim($request->request->getString('justification'));
        if ($entityRights['justification_transfer'] && $justification === '') {
            return self::redirectWithError(__("Justification required", "transferticketentity"));
        }

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
            $ticketCategory = (int) $entityRights['itilcategories_id'];
            $ticketUpdate['itilcategories_id'] = $ticketCategory;
        }

        // If category is mandatory with GLPIs template and category will be null
        if ($ticketCategory == 0 && self::checkMandatoryCategory($ticket, $entity)) {
            return self::redirectWithError(__(
                "Category will be set to null but its configured as mandatory in GLPIs template, please contact your administrator.",
                'transferticketentity'
            ));
        }

        $assignCriteria = [
            'tickets_id' => $ticket->getId(),
            'type' => CommonITILActor::ASSIGN,
        ];

        // Remove the link with the current users
        $ticketUser = new Ticket_User();
        foreach (array_keys($ticketUser->find($assignCriteria)) as $id) {
            $ticketUser->delete(['id' => $id]);
        }

        // Remove the link with the current groups
        $groupTicket = new Group_Ticket();
        foreach (array_keys($groupTicket->find($assignCriteria)) as $id) {
            $groupTicket->delete(['id' => $id]);
        }

        $ticket->update($ticketUpdate);

        if ($group) {
            $groupTicket->add($assignCriteria + ['groups_id' => $group->getId()]);
        }

        $justificationText = '';
        if ($justification !== '') {
            $justificationText = "<br> <br>" . htmlescape($justification);
        }
        $groupText = '';
        if ($group) {
            $groupText = __(" in the group ", "transferticketentity") . htmlescape($group->getName());
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
            ) . " " . htmlescape($entity->getName()) . $groupText . $justificationText,
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

    /**
     * Check if category exist in target entity
     */
    private static function checkExistingCategory(GlpiTicket $ticket, GlpiEntity $entity): bool
    {
        global $DB;

        $criteria = [
            'SELECT' => ['id'],
            'FROM' => ITILCategory::getTable(),
        ];
        $criteria = array_merge_recursive($criteria, getEntitiesRestrictCriteria(ITILCategory::getTable(), '', $entity->getId(), true));
        $categoryIdsInTargetEntity = array_column(iterator_to_array($DB->request($criteria), false), 'id');

        return in_array($ticket->fields['itilcategories_id'], $categoryIdsInTargetEntity);
    }

    /**
     * Check if category is mandatory in the ticket template used by target entity
     */
    private static function checkMandatoryCategory(GlpiTicket $ticket, GlpiEntity $entity): bool
    {
        $template = $ticket->getITILTemplateToUse(0, $ticket->fields['type'], 0, $entity->getId());

        return $template->isMandatoryField('itilcategories_id');
    }
}
