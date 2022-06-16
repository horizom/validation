<?php

namespace Horizom\Validation;

class Validation
{
    use Traits\SimplifyTrait;
    use Traits\FiltersTrait;
    use Traits\ValidatesTrait;
    use Traits\HelpersTrait;

    /**
     * Singleton instance of Validation.
     *
     * @var self|null
     */
    protected static $instance = null;

    /**
     * Contains readable field names that have been manually set.
     *
     * @var array
     */
    protected static $fields = [];

    /**
     * Custom validators.
     *
     * @var array
     */
    protected static $validation_methods = [];

    /**
     * Custom validators error messages.
     *
     * @var array
     */
    protected static $validation_methods_errors = [];

    /**
     * Customer filters.
     *
     * @var array
     */
    protected static $filter_methods = [];

    /**
     * Rules delimiter.
     *
     * @var string
     */
    public static $rules_delimiter = '|';

    /**
     * Rules-parameters delimiter.
     *
     * @var string
     */
    public static $rules_parameters_delimiter = ',';

    /**
     * Rules parameters array delimiter.
     *
     * @var string
     */
    public static $rules_parameters_arrays_delimiter = ';';

    /**
     * Characters that will be replaced to spaces during field name conversion (street_name => Street Name).
     *
     * @var array
     */
    public static $field_chars_to_spaces = ['_', '-'];

    public static $basic_tags = '<br><p><a><strong><b><i><em><img><blockquote><code><dd><dl><hr><h1><h2><h3><h4><h5><h6><label><ul><li><span><sub><sup>';

    public static $en_noise_words = "about,after,all,also,an,and,another,any,are,as,at,be,because,been,before,
                                     being,between,both,but,by,came,can,come,could,did,do,each,for,from,get,
                                     got,has,had,he,have,her,here,him,himself,his,how,if,in,into,is,it,its,it's,like,
                                     make,many,me,might,more,most,much,must,my,never,now,of,on,only,or,other,
                                     our,out,over,said,same,see,should,since,some,still,such,take,than,that,
                                     the,their,them,then,there,these,they,this,those,through,to,too,under,up,
                                     very,was,way,we,well,were,what,where,which,while,who,with,would,you,your,a,
                                     b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z,$,1,2,3,4,5,6,7,8,9,0,_";

    private static $alpha_regex = 'a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖßÙÚÛÜÝŸÑàáâãäåçèéêëìíîïðòóôõöùúûüýÿñ';

    public static $trues = ['1', 1, 'true', true, 'yes', 'on'];
    public static $falses = ['0', 0, 'false', false, 'no', 'off'];

    /**
     * Language for error messages.
     *
     * @var string
     */
    protected $lang;

    /**
     * Custom field-rule messages.
     *
     * @var array
     */
    protected $fields_error_messages = [];

    /**
     * Set of validation rules for execution.
     *
     * @var array
     */
    protected $validation_rules = [];

    /**
     * Set of filters rules for execution.
     *
     * @var array
     */
    protected $filter_rules = [];

    /**
     * Errors.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Gump constructor.
     *
     * @param string $lang
     * @throws ValidationException when language is not supported
     */
    public function __construct(string $lang = 'en')
    {
        $lang_file_location = __DIR__ . DIRECTORY_SEPARATOR . 'langs' . DIRECTORY_SEPARATOR . $lang . '.php';

        if (!EnvHelpers::file_exists($lang_file_location)) {
            throw new ValidationException(sprintf("'%s' language is not supported.", $lang));
        }

        $this->lang = $lang;
    }

    /**
     * Function to create and return previously created instance
     *
     * @return Validation
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Shorthand method for inline validation.
     *
     * @param array $data The data to be validated
     * @param array $validators The Gump validators
     * @param array $fields_error_messages
     * @return mixed True(boolean) or the array of error messages
     * @throws ValidationException If validation rule does not exist
     */
    public static function isValid(array $data, array $validators, array $fields_error_messages = [])
    {
        $gump = self::getInstance();
        $gump->validationRules($validators);
        $gump->setFieldsErrorMessages($fields_error_messages);

        if ($gump->run($data) === false) {
            return $gump->getReadableErrors();
        }

        return true;
    }

    /**
     * An empty value for us is: null, empty string or empty array
     *
     * @param  $value
     * @return bool
     */
    public static function isEmpty($value)
    {
        return (is_null($value) || $value === '' || (is_array($value) && count($value) === 0));
    }

    /**
     * Shorthand method for running only the data filters.
     *
     * @param array $data
     * @param array $filters
     * @return mixed
     * @throws ValidationException If filter does not exist
     */
    public static function filterInput(array $data, array $filters)
    {
        $gump = self::getInstance();
        return $gump->filter($data, $filters);
    }

    /**
     * Adds a custom validation rule using a callback function.
     *
     * @param string $rule
     * @param callable $callback
     * @param string $error_message
     *
     * @return void
     * @throws ValidationException when validator with the same name already exists
     */
    public static function addValidator(string $rule, callable $callback, string $error_message)
    {
        if (method_exists(__CLASS__, self::validator_to_method($rule)) || isset(self::$validation_methods[$rule])) {
            throw new ValidationException(sprintf("'%s' validator is already defined.", $rule));
        }

        self::$validation_methods[$rule] = $callback;
        self::$validation_methods_errors[$rule] = $error_message;
    }

    /**
     * Adds a custom filter using a callback function.
     *
     * @param string $rule
     * @param callable $callback
     *
     * @return void
     * @throws ValidationException when filter with the same name already exists
     */
    public static function addFilter(string $rule, callable $callback)
    {
        if (method_exists(__CLASS__, self::filter_to_method($rule)) || isset(self::$filter_methods[$rule])) {
            throw new ValidationException(sprintf("'%s' filter is already defined.", $rule));
        }

        self::$filter_methods[$rule] = $callback;
    }

    /**
     * Helper method to extract an element from an array safely
     *
     * @param  mixed $key
     * @param  array $array
     * @param  mixed $default
     *
     * @return mixed
     */
    public static function field($key, array $array, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        return $default;
    }

    /**
     * Getter/Setter for the filter rules.
     *
     * @param array $rules
     * @return array
     */
    public function filterRules(array $rules = [])
    {
        if (empty($rules)) {
            return $this->filter_rules;
        }

        $this->filter_rules = $rules;
    }

    /**
     * Run the filtering and validation after each other.
     *
     * @param array  $data
     * @param bool   $check_fields
     *
     * @return array|bool
     * @throws ValidationException
     */
    public function run(array $data, $check_fields = false)
    {
        $data = $this->filter($data, $this->filterRules());

        $validated = $this->validate($data, $this->validationRules());

        if ($check_fields === true) {
            $this->check_fields($data);
        }

        if ($validated !== true) {
            return false;
        }

        return $data;
    }

    /**
     * Sanitize the input data.
     *
     * @param array $input
     * @param array $fields
     * @param bool $utf8_encode
     *
     * @return array
     */
    public function sanitize(array $input, array $fields = [], bool $utf8_encode = true)
    {
        if (empty($fields)) {
            $fields = array_keys($input);
        }

        $return = [];

        foreach ($fields as $field) {
            if (!isset($input[$field])) {
                continue;
            }

            $value = $input[$field];
            if (is_array($value)) {
                $value = $this->sanitize($value, [], $utf8_encode);
            }
            if (is_string($value)) {
                if (strpos($value, "\r") !== false) {
                    $value = trim($value);
                }

                if (function_exists('iconv') && function_exists('mb_detect_encoding') && $utf8_encode) {
                    $current_encoding = mb_detect_encoding($value);

                    if ($current_encoding !== 'UTF-8' && $current_encoding !== 'UTF-16') {
                        $value = iconv($current_encoding, 'UTF-8', $value);
                    }
                }

                $value = filter_var($value, FILTER_UNSAFE_RAW);
            }

            $return[$field] = $value;
        }

        return $return;
    }

    /**
     * Return the error array from the last validation run.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Perform data validation against the provided ruleset.
     *
     * @param array $input Input data.
     * @param array $ruleset Validation rules.
     *
     * @return bool|array Returns bool true when no errors. Returns array when errors.
     * @throws ValidationException
     */
    public function validate(array $input, array $ruleset)
    {
        $this->errors = [];

        foreach ($ruleset as $field => $rawRules) {
            $input[$field] = ArrayHelpers::data_get($input, $field);

            $rules = $this->parse_rules($rawRules);
            $is_required = $this->field_has_required_rules($rules);

            if (!$is_required && self::isEmpty($input[$field])) {
                continue;
            }

            foreach ($rules as $rule) {
                $parsed_rule = $this->parse_rule($rule);
                $result = $this->foreach_call_validator($parsed_rule['rule'], $field, $input, $parsed_rule['param']);

                if (is_array($result)) {
                    $this->errors[] = $result;
                    break; // exit on first error
                }
            }
        }

        return (count($this->errors) > 0) ? $this->errors : true;
    }

    /**
     * Set a readable name for a specified field names.
     *
     * @param string $field
     * @param string $readable_name
     */
    public static function setFieldName(string $field, string $readable_name)
    {
        self::$fields[$field] = $readable_name;
    }

    /**
     * Set readable name for specified fields in an array.
     *
     * @param array $array
     */
    public static function setFieldNames(array $array)
    {
        foreach ($array as $field => $readable_name) {
            self::setFieldName($field, $readable_name);
        }
    }

    /**
     * Set a custom error message for a validation rule.
     *
     * @param string $rule
     * @param string $message
     */
    public static function setErrorMessage(string $rule, string $message)
    {
        self::$validation_methods_errors[$rule] = $message;
    }

    /**
     * Set custom error messages for validation rules in an array.
     *
     * @param array $array
     */
    public static function setErrorMessages(array $array)
    {
        foreach ($array as $rule => $message) {
            self::setErrorMessage($rule, $message);
        }
    }

    /**
     * Getter/Setter for the validation rules.
     *
     * @param array $rules
     * @return array
     */
    public function validationRules(array $rules = [])
    {
        if (empty($rules)) {
            return $this->validation_rules;
        }

        $this->validation_rules = $rules;
    }

    /**
     * Set field-rule specific error messages.
     *
     * @param array $fields_error_messages
     * @return array
     */
    public function setFieldsErrorMessages(array $fields_error_messages)
    {
        return $this->fields_error_messages = $fields_error_messages;
    }

    /**
     * Process the validation errors and return human readable error messages.
     *
     * @param bool   $convert_to_string = false
     * @param string $field_class
     * @param string $error_class
     * @return array|string
     * @throws ValidationException if validator doesn't have an error message to set
     */
    public function getReadableErrors(bool $convert_to_string = false, string $field_class = 'gump-field', string $error_class = 'gump-error-message')
    {
        if (empty($this->errors)) {
            return $convert_to_string ? '' : [];
        }

        $messages = $this->getMessages();
        $result = [];

        $transformer = static function ($replace) use ($field_class) {
            $replace['{field}'] = sprintf('<span class="%s">%s</span>', $field_class, $replace['{field}']);
            return $replace;
        };

        foreach ($this->errors as $error) {
            $message = $this->get_error_message($messages, $error['field'], $error['rule']);
            $result[] = $this->process_error_message($error['field'], $error['params'], $message, $transformer);
        }

        if ($convert_to_string) {
            return array_reduce($result, static function ($prev, $next) use ($error_class) {
                return sprintf('%s<span class="%s">%s</span>', $prev, $error_class, $next);
            });
        }

        return $result;
    }

    /**
     * Process the validation errors and return an array of errors with field names as keys.
     *
     * @return array
     * @throws ValidationException
     */
    public function getErrors()
    {
        $messages = $this->getMessages();
        $result = [];

        foreach ($this->errors as $error) {
            $message = $this->get_error_message($messages, $error['field'], $error['rule']);
            $result[$error['field']] = $this->process_error_message($error['field'], $error['params'], $message);
        }

        return $result;
    }

    /**
     * Filter the input data according to the specified filter set.
     *
     * @param mixed  $input
     * @param array  $filterset
     * @return mixed
     * @throws ValidationException
     */
    public function filter(array $input, array $filterset)
    {
        foreach ($filterset as $field => $filters) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $filters = $this->parse_rules($filters);

            foreach ($filters as $filter) {
                $parsed_rule = $this->parse_rule($filter);

                if (is_array($input[$field])) {
                    $input_array = &$input[$field];
                } else {
                    $input_array = array(&$input[$field]);
                }

                foreach ($input_array as &$value) {
                    $value = $this->call_filter($parsed_rule['rule'], $value, $parsed_rule['param']);
                }

                unset($input_array, $value);
            }
        }

        return $input;
    }

    /**
     * Perform XSS clean to prevent cross site scripting.
     *
     * @param array $data
     * @return array
     */
    public static function xssClean(array $data)
    {
        foreach ($data as $k => $v) {
            $data[$k] = filter_var($v, FILTER_UNSAFE_RAW);
        }

        return $data;
    }

    /**
     * Magic method to generate the validation error messages.
     *
     * @return string
     * @throws ValidationException
     */
    public function __toString()
    {
        return $this->getReadableErrors(true);
    }
}
