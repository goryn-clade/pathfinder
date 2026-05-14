<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 21.02.15
 * Time: 00:12
 */

namespace Exodus4D\Pathfinder\Exception;


class ValidationException extends PathfinderException {

    /**
     * @var array<string, mixed>
     */
    protected $codes = [
        2000 => 422
    ];

    /**
     * ValidationException constructor.
     * @param string $message
     * @param string $field
     */
    public function __construct(string $message, /**
     * table column that triggers the exception
     */
    private readonly string $field = ''){
        parent::__construct($message, 2000);
    }

    /**
     * get error object
     * @return \stdClass
     */
    #[\Override]
    public function getError() : \stdClass {
        $error = parent::getError();
        $error->field = $this->field;
        return $error;
    }
} 