<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3_MODE') or die('¯\_(ツ)_/¯');

ExtensionUtility::configurePlugin(
    'MichielRoos.h5p',
    'view',
    [
        'View' => 'index',
    ],
    [
        'View' => 'index',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'MichielRoos.h5p',
    'embedded',
    [
        'View' => 'embedded',
    ],
    [
        'View' => 'embedded',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'MichielRoos.h5p',
    'statistics',
    [
        'View' => 'statistics',
    ],
    [
        'View' => 'statistics',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'MichielRoos.h5p',
    'ajax',
    [
        'Ajax' => 'index,finish,contentUserData',
    ],
    [
        'Ajax' => 'index,finish,contentUserData',
    ]
);

ExtensionUtility::registerTypeConverter(\MichielRoos\H5p\Property\TypeConverter\UploadedFileReferenceConverter::class);
ExtensionUtility::registerTypeConverter(\MichielRoos\H5p\Property\TypeConverter\ObjectStorageConverter::class);


// InsertH5p button for editor
//$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['InsertH5p'] = [];
//$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['InsertH5p']['objectReference'] = \MichielRoos\H5p\Rtehtmlarea\Extension\InsertH5p::class;
//$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['rtehtmlarea']['plugins']['InsertH5p']['disableInFE'] = 0;

// load Backend JavaScript modules - Seem not to be called in backend record edit mode
//$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/template.php']['preStartPageHook'][] = \MichielRoos\H5p\Backend\BackendJsLoader::class . '->loadJsModules';
//$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/backend.php']['constructPostProcess'][] = \MichielRoos\H5p\Backend\BackendJsLoader::class . '->loadJsModules';
