<?php

namespace Horizom\Validation\Traits;

use Horizom\Validation\ValidationException;

trait HelpersTrait
{
    /**
     * Get all error messages.
     *
     * @return array
     */
    protected function getMessages()
    {
        $ds = DIRECTORY_SEPARATOR;
        $lang_file = dirname(__DIR__) . $ds . "langs" . $ds . $this->lang . '.php';
        $messages = include $lang_file;

        return array_merge($messages, self::$validation_methods_errors);
    }

    /**
     * Ensure that the field counts match the validation rule counts.
     *
     * @param array $data
     */
    private function check_fields(array $data)
    {
        $ruleset = $this->validationRules();
        $mismatch = array_diff_key($data, $ruleset);
        $fields = array_keys($mismatch);

        foreach ($fields as $field) {
            $this->errors[] = $this->generate_error_array($field, $data[$field], 'mismatch');
        }
    }

    /**
     * Parses filters and validators rules group.
     *
     * @param string|array $rules
     * @return array
     */
    private function parse_rules($rules)
    {
        // v2
        if (is_array($rules)) {
            $rules_names = [];
            foreach ($rules as $key => $value) {
                $rules_names[] = is_numeric($key) ? $value : $key;
            }

            return array_map(function ($value, $key) use ($rules) {
                if ($value === $key) {
                    return [$key];
                }

                return [$key, $value];
            }, $rules, $rules_names);
        }

        return explode(self::$rules_delimiter, $rules);
    }

    /**
     * Parses filters and validators individual rules.
     *
     * @param string|array $rule
     * @return array
     */
    private function parse_rule($rule)
    {
        // v2
        if (is_array($rule)) {
            return [
                'rule' => $rule[0],
                'param' => $this->parse_rule_params($rule[1] ?? [])
            ];
        }

        $result = [
            'rule' => $rule,
            'param' => []
        ];

        if (strpos($rule, self::$rules_parameters_delimiter) !== false) {
            list($rule, $param) = explode(self::$rules_parameters_delimiter, $rule);

            $result['rule'] = $rule;
            $result['param'] = $this->parse_rule_params($param);
        }

        return $result;
    }

    /**
     * Parse rule parameters.
     *
     * @param string|array $param
     * @return array|string|null
     */
    private function parse_rule_params($param)
    {
        if (is_array($param)) {
            return $param;
        }

        if (strpos($param, self::$rules_parameters_arrays_delimiter) !== false) {
            return explode(self::$rules_parameters_arrays_delimiter, $param);
        }

        return [$param];
    }

    /**
     * Checks if array of rules contains a required type of validator.
     *
     * @param array $rules
     * @return bool
     */
    private function field_has_required_rules(array $rules)
    {
        $require_type_of_rules = ['required', 'required_file'];

        // v2
        if (is_array($rules) && is_array($rules[0])) {
            $found = array_filter($rules, function ($item) use ($require_type_of_rules) {
                return in_array($item[0], $require_type_of_rules);
            });
            return count($found) > 0;
        }

        $found = array_values(array_intersect($require_type_of_rules, $rules));
        return count($found) > 0;
    }

    /**
     * Helper to convert validator rule name to validator rule method name.
     *
     * @param string $rule
     * @return string
     */
    private static function validator_to_method(string $rule)
    {
        return sprintf('validate_%s', $rule);
    }

    /**
     * Helper to convert filter rule name to filter rule method name.
     *
     * @param string $rule
     * @return string
     */
    private static function filter_to_method(string $rule)
    {
        return sprintf('filter_%s', $rule);
    }

    /**
     * Calls call_validator.
     *
     * @param string $rule
     * @param string $field
     * @param mixed $input
     * @param array $rule_params
     * @return array|bool
     * @throws ValidationException
     */
    private function foreach_call_validator(string $rule, string $field, array $input, array $rule_params = [])
    {
        $values = !is_array($input[$field]) ? [$input[$field]] : $input[$field];

        foreach ($values as $value) {
            $result = $this->call_validator($rule, $field, $input, $rule_params, $value);
            if (is_array($result)) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Calls a validator.
     *
     * @param string $rule
     * @param string $field
     * @param mixed $input
     * @param array $rule_params
     * @return array|bool
     * @throws ValidationException
     */
    private function call_validator(string $rule, string $field, array $input, array $rule_params = [], $value = null)
    {
        $method = self::validator_to_method($rule);

        // use native validations
        if (is_callable([$this, $method])) {
            $result = $this->$method($field, $input, $rule_params, $value);

            // is_array check for backward compatibility
            return (is_array($result) || $result === false)
                ? $this->generate_error_array($field, $input[$field], $rule, $rule_params)
                : true;
        }

        // use custom validations
        if (isset(self::$validation_methods[$rule])) {
            $result = call_user_func(self::$validation_methods[$rule], $field, $input, $rule_params, $value);

            return ($result === false)
                ? $this->generate_error_array($field, $input[$field], $rule, $rule_params)
                : true;
        }

        throw new ValidationException(sprintf("'%s' validator does not exist.", $rule));
    }

    /**
     * Calls a filter.
     *
     * @param string $rule
     * @param mixed $value
     * @param array $rule_params
     * @return mixed
     * @throws ValidationException
     */
    private function call_filter(string $rule, $value, array $rule_params = [])
    {
        $method = self::filter_to_method($rule);

        // use native filters
        if (is_callable(array($this, $method))) {
            return $this->$method($value, $rule_params);
        }

        // use custom filters
        if (isset(self::$filter_methods[$rule])) {
            return call_user_func(self::$filter_methods[$rule], $value, $rule_params);
        }

        // use php functions as filters
        if (function_exists($rule)) {
            return call_user_func($rule, $value, ...$rule_params);
        }

        throw new ValidationException(sprintf("'%s' filter does not exist.", $rule));
    }

    /**
     * Generates error array.
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param array $rule_params
     * @return array
     */
    private function generate_error_array(string $field, $value, string $rule, array $rule_params = [])
    {
        return [
            'field' => $field,
            'value' => $value,
            'rule' => $rule,
            'params' => $rule_params
        ];
    }

    /**
     * Get error message.
     *
     * @param array $messages
     * @param string $field
     * @param string $rule
     * @return mixed|null
     * @throws ValidationException
     */
    private function get_error_message(array $messages, string $field, string $rule)
    {
        $custom_error_message = $this->get_custom_error_message($field, $rule);
        if ($custom_error_message !== null) {
            return $custom_error_message;
        }

        if (isset($messages[$rule])) {
            return $messages[$rule];
        }

        throw new ValidationException(sprintf("'%s' validator does not have an error message.", $rule));
    }

    /**
     * Get custom error message for field and rule.
     *
     * @param string $field
     * @param string $rule
     * @return string|null
     */
    private function get_custom_error_message(string $field, string $rule)
    {
        $rule_name = str_replace('validate_', '', $rule);
        return $this->fields_error_messages[$field][$rule_name] ?? null;
    }

    /**
     * Process error message string.
     *
     * @param $field
     * @param array $params
     * @param string $message
     * @param callable|null $transformer
     * @return string
     */
    private function process_error_message($field, array $params, string $message, callable $transformer = null)
    {
        // if field name is explicitly set, use it
        if (array_key_exists($field, self::$fields)) {
            $field = self::$fields[$field];
        } else {
            $field = ucwords(str_replace(self::$field_chars_to_spaces, chr(32), $field));
        }

        // if param is a field (i.e. equalsfield validator)
        if (isset($params[0]) && array_key_exists($params[0], self::$fields)) {
            $params[0] = self::$fields[$params[0]];
        }

        $replace = [
            '{field}' => $field,
            '{param}' => implode(', ', $params)
        ];

        foreach ($params as $key => $value) {
            $replace[sprintf('{param[%s]}', $key)] = $value;
        }

        // for getReadableErrors() <span>
        if ($transformer) {
            $replace = $transformer($replace);
        }

        return strtr($message, $replace);
    }
}
