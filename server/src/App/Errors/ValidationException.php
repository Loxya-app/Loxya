<?php
namespace Robert2\API\Errors;

use Illuminate\Database\QueryException;

class ValidationException extends \Exception
{
    private $_validation;
    protected $code;

    public function __construct(int $code = ERROR_VALIDATION)
    {
        $this->code = $code;

        $message = "Validation failed. See error[details] for more informations.";

        parent::__construct($message, $code, null);
    }

    // ------------------------------------------------------
    // -
    // -    Setters
    // -
    // ------------------------------------------------------

    public function setValidationErrors($validation): self
    {
        $this->_validation = (array)$validation;

        return $this;
    }

    /**
     * Parses PDO error codes to translate them into error exceptions
     */
    public function setPDOValidationException(QueryException $e): self
    {
        $message = "";
        $details = $e->getMessage();

        if (isDuplicateException($e)) {
            $this->code = ERROR_DUPLICATE;
            $offsetKeyName = strripos($details, "for key '") + strlen("for key '");
            $offsetEnd = strripos($details, "' (SQL:");
            $keyName = substr($details, $offsetKeyName, $offsetEnd - $offsetKeyName);
            $keyNameWithoutUnique = str_replace('_UNIQUE', '', $keyName);
            $message = "Duplicate entry: value for index '$keyNameWithoutUnique' must be unique";
        }
        $this->setValidationErrors([$message, $details]);

        return $this;
    }

    // ------------------------------------------------------
    // -
    // -    Getters
    // -
    // ------------------------------------------------------

    public function getValidationErrors()
    {
        return $this->_validation;
    }
}
