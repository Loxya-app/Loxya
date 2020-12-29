<?php
declare(strict_types=1);

namespace Robert2\API\Models\Traits;

use Robert2\Lib\Pdf\Pdf;
use Robert2\API\I18n\I18n;
use Robert2\API\Config\Config;
use Robert2\API\Errors\NotFoundException;

trait WithPdf
{
    protected $pdfTemplate = null;

    // ------------------------------------------------------
    // -
    // -    Getters
    // -
    // ------------------------------------------------------

    public function getPdfName(int $id): string
    {
        $model = $this->withTrashed()->find($id);
        if (!$model) {
            throw new NotFoundException(sprintf('Record %d not found.', $id));
        }

        $company = Config::getSettings('companyData');

        $i18n = new I18n(Config::getSettings('defaultLang'));
        $fileName = sprintf(
            '%s-%s-%s.pdf',
            $i18n->translate(class_basename($this)),
            slugify($company['name']),
            $model->title ?: $model->id
        );
        if (isTestMode()) {
            $fileName = sprintf('TEST-%s', $fileName);
        }

        return $fileName;
    }

    // ------------------------------------------------------
    // -
    // -    Internal Methods
    // -
    // ------------------------------------------------------

    protected function _getPdfAsString(array $data): string
    {
        if (empty($this->pdfTemplate)) {
            throw new \RuntimeException("Missing model's PDF template name");
        }

        return Pdf::createFromTemplate($this->pdfTemplate, $data);
    }
}
