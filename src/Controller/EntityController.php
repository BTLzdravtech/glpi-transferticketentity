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

        $entity_id = $request->request->getInt('entity_id', -1);
        $entity = new GlpiEntity();
        $entity->check($entity_id, UPDATE);

        $itilcategories_id = $request->request->getInt('itilcategories_id');

        $DB->updateOrInsert(
            'glpi_plugin_transferticketentity_entities_settings',
            [
                'allow_entity_only_transfer' => $request->request->getBoolean('allow_entity_only_transfer'),
                'justification_transfer' => $request->request->getBoolean('justification_transfer'),
                'allow_transfer' => $request->request->getBoolean('allow_transfer'),
                'keep_category' => $request->request->getBoolean('keep_category'),
                'itilcategories_id' => $itilcategories_id > 0 ? $itilcategories_id : null,
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
