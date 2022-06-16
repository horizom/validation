<?php

namespace Horizom\Validation\Traits;

trait SimplifyTrait
{
    /**
     * Getter/Setter filters rules
     */
    public function filters(array $rulesSet)
    {
        return $this->filterRules($rulesSet);
    }

    /**
     * Getter/Setter validation rules
     */
    public function rules(array $rulesSet)
    {
        return $this->validationRules($rulesSet);
    }

    /**
     * Getter/Setter validation fields error messages
     */
    public function messages(array $messagesFields)
    {
        return $this->setFieldsErrorMessages($messagesFields);
    }
}
