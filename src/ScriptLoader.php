<?php

namespace Sanjeev\Custom;

use Krishna\DataValidator\Validator;

\Sanjeev\Custom\ErrorReporting::init();

class ScriptLoader
{
    public static $error = null;
    private static ?Validator $_v = null;
    public static ?array $data = null;
    protected static ?string $nameSpace = null;
    protected static mixed $props = [];
    private static string $dirPath = __DIR__ . "/js/";
    private static bool $devMode = false;
    protected static string $scriptData = "";
    private function __construct()
    {
    }
    private static function echo_error($value)
    {
        header("Content-type: application/javascript");
        if (is_string($value)) {
            echo 'console.error("', $value, '");';
        } else {
            echo 'console.error(', json_encode($value), ');';
        }
        exit;
    }
    private static function _init_()
    {
        if (!isset($_SERVER['HTTP_REFERER']) && static::$devMode === false) {
            static::echo_error('Unauthorised Request');
        }
        static::$error = null;
        $v = Validator::create([
            'script' => 'string',
            '?format' => 'string',
            '?apiKey' => 'string@str_range(16,16)'
        ]);
        if ($v->valid) {
            static::$_v = $v->value;
        } else {
            static::$error = $v->value;
            return;
        }
        $data = static::$_v->validate($_GET);
        if ($data->valid) {
            static::$data = $data->value;
        } else {
            static::$error = $data->value;
        }
    }
    public static function load(?string $nameSpace = null, ?string $dirPath = null)
    {
        static::_init_();
        if (static::$error !== null) {
            static::echo_error(static::$error);
        }
        if ($dirPath !== null) {
            static::$dirPath = $dirPath;
        }

        if ($nameSpace !== null) {
            $nameSpace = "\\" . $nameSpace . "\\";
            static::$nameSpace = $nameSpace;
        }
        //Set Additional Props here
        static::$props['__apiKey'] = static::$data['apiKey'] ?? null;
        //End
        if (isset(static::$data['format'])) {
            $ext = static::$data['format'];
        } else {
            $ext = "js";
        }
        $fileName = static::$data['script'] . "." . $ext;
        static::loadFile(fileName: $fileName);
        $className = static::$nameSpace . static::$data['script'];
        if ("WebComponent" === ($className::$type ?? null)) {
            static::generateProps();
        }
        static::render();
    }
    protected static function loadFile(string $fileName, bool $includeDirPath = false)
    {
        $filePath = $includeDirPath ? static::$dirPath . '\\' . $fileName : $fileName;
        if (file_exists($filePath)) {
            static::$scriptData = file_get_contents($filePath);
        } else {
            static::echo_error("Unknown Script Requested");
        }
    }
    protected static function generateProps()
    {
        $props = [];
        $scriptName = static::$data['script'];
        foreach (array_merge(static::$props, static::requestProps()) as $k => $v) {
            $props[$k] = [
                'value' => $v,
                'enumerable' => false,
                'writable' => false
            ];
        }
        $props = json_encode($props);
        static::$scriptData .= <<<sc

        Object.defineProperties({$scriptName}, {$props});
        customElements.define({$scriptName}.__tag, {$scriptName});
        sc;
    }
    protected static function requestProps(string $nameSpace = null)
    {
        $className = static::$nameSpace . static::$data['script'];
        if (class_exists($className)) {
            return $className::props();
        }
        return [];
    }
    protected static function render()
    {
        header("Content-type: application/javascript");
        echo static::$scriptData;
        exit;
    }
}
