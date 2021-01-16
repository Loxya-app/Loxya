<?php
declare(strict_types=1);

namespace Robert2\API\Controllers;

use Robert2\API\Errors;
use Robert2\API\Controllers\Traits\WithPdf;
use Robert2\API\Models\Park;
use Slim\Http\Request;
use Slim\Http\Response;

class EventController extends BaseController
{
    use WithPdf;

    // ——————————————————————————————————————————————————————
    // —
    // —    Getters
    // —
    // ——————————————————————————————————————————————————————

    public function getAll(Request $request, Response $response): Response
    {
        $startDate = $request->getQueryParam('start', null);
        $endDate = $request->getQueryParam('end', null);
        $deleted = (bool)$request->getQueryParam('deleted', false);

        $results = $this->model
            ->setPeriod($startDate, $endDate)
            ->getAll($deleted);

        $data = $results->get()->toArray();
        $useMultipleParks = Park::count() > 1;
        foreach ($data as $index => $event) {
            $eventMissingMaterials = $this->model->getMissingMaterials($event['id']);
            $data[$index]['has_missing_materials'] = !empty($eventMissingMaterials);
            $data[$index]['parks'] = $useMultipleParks ? $this->model->getParks($event['id']) : null;
        }

        return $response->withJson([ 'data' => $data ]);
    }

    public function getOne(Request $request, Response $response): Response
    {
        $id = (int)$request->getAttribute('id');
        if (!$this->model->exists($id)) {
            throw new Errors\NotFoundException;
        }
        return $response->withJson($this->_getFormattedEvent($id));
    }

    public function getMissingMaterials(Request $request, Response $response): Response
    {
        $id = (int)$request->getAttribute('id');
        if (!$this->model->exists($id)) {
            throw new Errors\NotFoundException;
        }

        $eventMissingMaterials = $this->model->getMissingMaterials($id);
        if (empty($eventMissingMaterials)) {
            return $response->withJson([]);
        }

        return $response->withJson($eventMissingMaterials);
    }

    // ------------------------------------------------------
    // -
    // -    Setters
    // -
    // ------------------------------------------------------

    public function create(Request $request, Response $response): Response
    {
        $postData = $request->getParsedBody();
        $id = $this->_saveEvent(null, $postData);

        return $response->withJson($this->_getFormattedEvent($id), SUCCESS_CREATED);
    }

    public function update(Request $request, Response $response): Response
    {
        $id = (int)$request->getAttribute('id');
        $model = $this->model->find($id);
        if (!$model) {
            throw new Errors\NotFoundException;
        }

        $postData = $request->getParsedBody();
        $id = $this->_saveEvent($id, $postData);

        return $response->withJson($this->_getFormattedEvent($id), SUCCESS_OK);
    }

    // ——————————————————————————————————————————————————————
    // —
    // —    Internal Methods
    // —
    // ——————————————————————————————————————————————————————

    protected function _saveEvent(?int $id, array $postData): int
    {
        if (empty($postData)) {
            throw new \InvalidArgumentException(
                "Missing request data to process validation",
                ERROR_VALIDATION
            );
        }

        $result = $this->model->edit($id, $postData);

        if (isset($postData['beneficiaries'])) {
            $result->Beneficiaries()->sync($postData['beneficiaries']);
        }

        if (isset($postData['assignees'])) {
            $result->Assignees()->sync($postData['assignees']);
        }

        if (isset($postData['materials'])) {
            $materials = [];
            foreach ($postData['materials'] as $material) {
                if ((int)$material['quantity'] <= 0) {
                    continue;
                }

                $materials[$material['id']] = [
                    'quantity' => $material['quantity']
                ];
            }
            $result->Materials()->sync($materials);
        }

        return $result->id;
    }

    protected function _getFormattedEvent(int $id): array
    {
        $model = $this->model
            ->with('User')
            ->with('Assignees')
            ->with('Beneficiaries')
            ->with('Materials')
            ->with('Bills')
            ->find($id);

        $result = $model->toArray();
        if (!$model->bills) {
            return $result;
        }

        $result['bills'] = $model->bills;
        return $result;
    }
}
