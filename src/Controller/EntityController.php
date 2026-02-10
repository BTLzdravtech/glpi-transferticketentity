<?php

namespace GlpiPlugin\Transferticketentity\Controller;

use Entity as GlpiEntity;
use Glpi\Controller\AbstractController;
use Glpi\Http\RedirectResponse;
use Html;
use Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EntityController extends AbstractController
{
    #[Route("/front/entity.form.php", name: "transferticketentity_entity")]
    public function __invoke(Request $request): Response
    {
        global $DB;

        Session::haveRightsOr(GlpiEntity::$rightname, [CREATE, UPDATE, PURGE]);

        $entity_id = $_POST['entity_id'];
        $entity = new GlpiEntity();
        $entity->getFromDB($entity_id);

        $DB->updateOrInsert(
            'glpi_plugin_transferticketentity_entities_settings',
            [
                'allow_entity_only_transfer' => $_POST['allow_entity_only_transfer'],
                'justification_transfer' => $_POST['justification_transfer'],
                'allow_transfer' => $_POST['allow_transfer'],
                'keep_category' => $_POST['keep_category'],
                'itilcategories_id' => $_POST['itilcategories_id'] == 0 ? null : $_POST['itilcategories_id'],
            ],
            [
                'entities_id' => $entity_id,
            ]
        );

        Session::addMessageAfterRedirect(
            __("Item successfully updated: ") . $entity->getName(),
            true,
            INFO
        );

        return new RedirectResponse(Html::getBackUrl());
    }
}
