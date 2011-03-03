<?php

if ( ! function_exists( 'assignConstant' ) )
{
	$exception = '';

	if ( defined( 'ENTITY_FUNCTION' ) )

		$exception = sprintf( EXCEPTION_MISSING_ENTITY, ENTITY_FUNCTION );

	throw new Exception( $exception );
}

// Entity names

assignConstant('ENTITY_ACTION', 'action');
assignConstant('ENTITY_ADMINISTRATOR', 'administrator');
assignConstant('ENTITY_ADMINISTRATION', 'administration');
assignConstant('ENTITY_AFFORDANCE', 'affordance');
assignConstant('ENTITY_AGENT', 'agent');
assignConstant('ENTITY_ALIAS', 'alias');
assignConstant('ENTITY_ALPHA', 'alpha');
assignConstant('ENTITY_ANY', 'any');
assignConstant('ENTITY_API', 'api');
assignConstant('ENTITY_APPLICATION', 'application');
assignConstant('ENTITY_ARC', 'arc');
assignConstant('ENTITY_ARCHIVE', 'archive');
assignConstant('ENTITY_AUTHOR', 'author');
assignConstant('ENTITY_AUTHORIZATION_SCOPE', 'authorization_scope');
assignConstant('ENTITY_ASSERTION', 'assertion');
assignConstant('ENTITY_BLACKBOARD', 'blackboard');
assignConstant('ENTITY_BLOCK', 'block');
assignConstant('ENTITY_BODY', 'body');
assignConstant('ENTITY_CACHE', 'cache');
assignConstant('ENTITY_CALLBACK', 'callback');
assignConstant('ENTITY_CHECK', 'check');
assignConstant('ENTITY_CLASS', 'class');
assignConstant('ENTITY_CLASS_NAME', 'class_name');
assignConstant('ENTITY_CODE', 'code');
assignConstant('ENTITY_COLUMN', 'column');
assignConstant('ENTITY_COLUMN_PREFIX', 'column_prefix');
assignConstant('ENTITY_COMPILATION', 'compilation');
assignConstant('ENTITY_CONFIGURATION', 'configuration');
assignConstant('ENTITY_CONNECTOR', 'connector');
assignConstant('ENTITY_CONSTANT', 'constant');
assignConstant('ENTITY_CONSTRAINT', 'constraint');
assignConstant('ENTITY_CONTAINER', 'container');
assignConstant('ENTITY_CONTENT', 'content');
assignConstant('ENTITY_CONTENT_LEVEL', 'content_level');
assignConstant('ENTITY_CONTENT_MANAGER', 'content_manager');
assignConstant('ENTITY_CONTEXT', 'context');
assignConstant('ENTITY_CONTROL_PANEL', 'control_panel');
assignConstant('ENTITY_CONTROLLER', 'controller');
assignConstant('ENTITY_CRAFTSMAN', 'craftsman');
assignConstant('ENTITY_CSS_CLASS', 'css_class');
assignConstant('ENTITY_DOM', 'dom');
assignConstant('ENTITY_DB', 'db');
assignConstant('ENTITY_DASHBOARD', 'dashboard');
assignConstant('ENTITY_DATABASE', 'database');
assignConstant('ENTITY_DATA_FETCHER', 'data_fetcher');
assignConstant('ENTITY_DEPLOYER', 'deployer');
assignConstant('ENTITY_DIALOG', 'dialog');
assignConstant('ENTITY_DIAPORAMA', 'diaporama');
assignConstant('ENTITY_DIRECTORY', 'directory');
assignConstant('ENTITY_DISCLAIMER', 'disclaimer');
assignConstant('ENTITY_DISPLAY', 'display');
assignConstant('ENTITY_DOCUMENT', 'document');
assignConstant('ENTITY_DOM', 'dom');
assignConstant('ENTITY_DOM_ATTRIBUTE', 'dom_attribute');
assignConstant('ENTITY_DOM_ELEMENT', 'dom_element');
assignConstant('ENTITY_DOM_DOCUMENT', 'dom_document');
assignConstant('ENTITY_DOM_ELEMENT', 'dom_element');
assignConstant('ENTITY_DOM_NODE', 'dom_node');
assignConstant('ENTITY_DOM_NODE_LIST', 'dom_node_list');
assignConstant('ENTITY_DOM_TEXT', 'dom_text');
assignConstant('ENTITY_DUMPER', 'dumper');
assignConstant('ENTITY_EDITOR', 'editor');
assignConstant('ENTITY_EDITION_MODE', 'edition_mode');
assignConstant('ENTITY_EDGE', 'edge');
assignConstant('ENTITY_ELEMENT', 'element');
assignConstant('ENTITY_ELEMENT_HTML', 'element_html');
assignConstant('ENTITY_EMAIL', 'email');
assignConstant('ENTITY_ENTITY', 'entity');
assignConstant('ENTITY_ENTITY_TYPE', 'entity_type');
assignConstant('ENTITY_ENVIRONMENT', 'environment');
assignConstant('ENTITY_ERROR', 'error');
assignConstant('ENTITY_EXECUTOR', 'executor');
assignConstant('ENTITY_EXCEPTION', 'exception');
assignConstant('ENTITY_EXCEPTION_HANDLER', 'exception_handler');
assignConstant('ENTITY_EVENT', 'event');
assignConstant('ENTITY_EVENT_MANAGER', 'event_manager');
assignConstant('ENTITY_FACTORY', 'factory');
assignConstant('ENTITY_FAILURE', 'failure');
assignConstant('ENTITY_FEEDBACK', 'feedback');
assignConstant('ENTITY_FEED_READER', 'feed_reader');
assignConstant('ENTITY_FIELD', 'field');
assignConstant('ENTITY_FIELD_HANDLER', 'field_handler');
assignConstant('ENTITY_FILE_EXTENSION', 'file_extension');
assignConstant('ENTITY_FILE_MANAGER', 'file_manager');
assignConstant('ENTITY_FLAG', 'flag');
assignConstant('ENTITY_FLAG_MANAGER', 'flag_manager');
assignConstant('ENTITY_FOLDER', 'folder');
assignConstant('ENTITY_FOOTER', 'footer');
assignConstant('ENTITY_FORM', 'form');
assignConstant('ENTITY_FORM_MANAGER', 'form_manager');
assignConstant('ENTITY_FUNCTION', 'function');
assignConstant('ENTITY_HEADER', 'header');
assignConstant('ENTITY_HELPER', 'helper');
assignConstant('ENTITY_HTML_INPUT', 'html_input');
assignConstant('ENTITY_HTML_SELECT', 'html_select');
assignConstant('ENTITY_HTML_TAG', 'html_tag');
assignConstant('ENTITY_HTML_TEXTAREA', 'html_textarea');
assignConstant('ENTITY_I18N', 'i18n');
assignConstant('ENTITY_IMAGE', 'image');
assignConstant('ENTITY_INSIGHT', 'insight');
assignConstant('ENTITY_INSIGHT_NODE', 'insight_node');
assignConstant('ENTITY_INSTANCE', 'instance');
assignConstant('ENTITY_INTERCEPTOR', 'interceptor');
assignConstant('ENTITY_JQUERY4PHP', 'jquery4php');
assignConstant('ENTITY_KEYWORD', 'keyword');
assignConstant('ENTITY_LABEL', 'label');
assignConstant('ENTITY_LANGUAGE', 'language');
assignConstant('ENTITY_LEAF', 'leaf');
assignConstant('ENTITY_LEVEL', 'level');
assignConstant('ENTITY_LINK', 'link');
assignConstant('ENTITY_LIST', 'list');
assignConstant('ENTITY_LIST_ITEM', 'list_item');
assignConstant('ENTITY_LOCK', 'lock');
assignConstant('ENTITY_LOCKSMITH', 'locksmith');
assignConstant('ENTITY_LSQL', 'lsql');
assignConstant('ENTITY_LAYOUT', 'layout');
assignConstant('ENTITY_LAYOUT_MANAGER', 'layout_manager');
assignConstant('ENTITY_LINK', 'link');
assignConstant('ENTITY_MANAGEMENT', 'management');
assignConstant('ENTITY_MEDIA_MANAGER', 'media_manager');
assignConstant('ENTITY_MEMBER', 'member');
assignConstant('ENTITY_MEMENTO', 'memento');
assignConstant('ENTITY_MESSAGE', 'message');
assignConstant('ENTITY_MESSENGER', 'messenger');
assignConstant('ENTITY_METHOD', 'method');
assignConstant('ENTITY_METHOD_NAME', 'method_name');
assignConstant('ENTITY_METHOD_NAME_SECTION', 'method_name_section');
assignConstant('ENTITY_MENU', 'menu');
assignConstant('ENTITY_MESSAGE', 'message');
assignConstant('ENTITY_MICROBLOGGING', 'microblogging');
assignConstant('ENTITY_MYSQLI', 'mysqli');
assignConstant('ENTITY_NAME', 'name');
assignConstant('ENTITY_NAME_CLASS', 'class_name');
assignConstant('ENTITY_NAME_METHOD', 'method_name');
assignConstant('ENTITY_NODE', 'node');
assignConstant('ENTITY_OAUTH', 'oauth');
assignConstant('ENTITY_OAUTH_SECRET', 'oauth_secret');
assignConstant('ENTITY_OBJECT', 'object');
assignConstant('ENTITY_OBJECT_BUILDER', 'object_builder');
assignConstant('ENTITY_OBSERVATION', 'observation');
assignConstant('ENTITY_OPERATION', 'operation');
assignConstant('ENTITY_OPTIMIZER', 'optimizer');
assignConstant('ENTITY_OPTION', 'option');
assignConstant('ENTITY_ORDER', 'order');
assignConstant('ENTITY_OVERVIEW', 'overview');
assignConstant('ENTITY_PAGE', 'page');
assignConstant('ENTITY_PANEL', 'panel');
assignConstant('ENTITY_PAPER_MAKER', 'paper_maker');
assignConstant('ENTITY_PARSER', 'parser');
assignConstant('ENTITY_PATH', 'path');
assignConstant('ENTITY_PATTERN', 'pattern');
assignConstant('ENTITY_PDO', 'pdo');
assignConstant('ENTITY_PLACEHOLDER', 'placeholder');
assignConstant('ENTITY_PLAN', 'plan');
assignConstant('ENTITY_PHOTO', 'photo');
assignConstant('ENTITY_PHOTOGRAPH', 'photograph');
assignConstant('ENTITY_PHP_VARIABLE', 'PHP_variable');
assignConstant('ENTITY_PREFIX', 'prefix');
assignConstant('ENTITY_PREPOSITION', 'preposition');
assignConstant('ENTITY_PROCESSOR', 'processor');
assignConstant('ENTITY_PROPERTY', 'property');
assignConstant('ENTITY_PROVER', 'prover');
assignConstant('ENTITY_QUERY', 'query');
assignConstant('ENTITY_QUERY_LANGUAGE', 'query_language');
assignConstant('ENTITY_RAW_CONTENTS', 'raw_contents');
assignConstant('ENTITY_RESOURCE', 'resource');
assignConstant('ENTITY_ROAD', 'road');
assignConstant('ENTITY_ROOT', 'root');
assignConstant('ENTITY_ROUTE', 'route');
assignConstant('ENTITY_ROUTER', 'router');
assignConstant('ENTITY_SLICE', 'slice');
assignConstant('ENTITY_SECTION', 'section');
assignConstant('ENTITY_SEPARATOR', 'separator');
assignConstant('ENTITY_SERIALIZER', 'serializer');
assignConstant('ENTITY_SERVICE', 'service');
assignConstant('ENTITY_SERVICE_MANAGER', 'service_manager');
assignConstant('ENTITY_SMARTY_SEFI', 'smarty_sefi');
assignConstant('ENTITY_SMARTY_VARIABLE', 'Smarty_variable');
assignConstant('ENTITY_SNAPSHOT', 'snapshot');
assignConstant('ENTITY_SOCIAL_NETWORK', 'social_network');
assignConstant('ENTITY_SOURCE', 'source');
assignConstant('ENTITY_SPARQL', 'sparql');
assignConstant('ENTITY_SQL', 'sql');
assignConstant('ENTITY_STANDARD', 'standard');
assignConstant('ENTITY_STANDARD_CLASS', ENTITY_STANDARD);
assignConstant('ENTITY_STEP', 'step');
assignConstant('ENTITY_STORE', 'store');
assignConstant('ENTITY_STORE_ITEM', 'store_item');
assignConstant('ENTITY_STORAGE', 'storage');
assignConstant('ENTITY_STREAM', 'stream');
assignConstant('ENTITY_STYLESHEET', 'stylesheet');
assignConstant('ENTITY_SUCCESS', 'success');
assignConstant('ENTITY_SYNCHRONIZATION', 'synchronization');
assignConstant('ENTITY_SYNCING', 'syncing');
assignConstant('ENTITY_TABLE', 'table');
assignConstant('ENTITY_TABLE_ALIAS', 'table_alias');
assignConstant('ENTITY_TAB', 'tab');
assignConstant('ENTITY_TAG', 'tag');
assignConstant('ENTITY_TAG_FORM', 'tag_form');
assignConstant('ENTITY_TAG_FIELDSET', 'tag_fieldset');
assignConstant('ENTITY_TAG_DIV', 'tag_div');
assignConstant('ENTITY_TAG_FORM', 'tag_form');
assignConstant('ENTITY_TAG_HTML', 'tag_html');
assignConstant('ENTITY_TAG_INPUT', 'tag_input');
assignConstant('ENTITY_TAG_P', 'tag_p');
assignConstant('ENTITY_TAG_SELECT', 'tag_select');
assignConstant('ENTITY_TAG_SPAN', 'tag_span');
assignConstant('ENTITY_TAG_TEXTAREA', 'tag_textarea');
assignConstant('ENTITY_TARGET', 'target');
assignConstant('ENTITY_TEMPLATE', 'template');
assignConstant('ENTITY_TEMPLATE_ENGINE', 'template_engine');
assignConstant('ENTITY_TEST', 'test');
assignConstant('ENTITY_TEST_CASE', 'test_case');
assignConstant('ENTITY_TWITTEROAUTH', 'twitteroauth');
assignConstant('ENTITY_TEXT', 'text');
assignConstant('ENTITY_THREAD', 'thread');
assignConstant('ENTITY_TITLE', 'title');
assignConstant('ENTITY_TOKEN', 'token');
assignConstant('ENTITY_TOOLBOX', 'toolbox');
assignConstant('ENTITY_TRANSFER', 'transfer');
assignConstant('ENTITY_TYPE', 'type');
assignConstant('ENTITY_URI', 'URI');
assignConstant('ENTITY_UNIT_TESTING', 'unit_testing');
assignConstant('ENTITY_USER', 'user');
assignConstant('ENTITY_USER_NAME', 'user_name');
assignConstant('ENTITY_USER_HANDLER', 'user_handler');
assignConstant('ENTITY_USER_INTERFACE', 'user_interface');
assignConstant('ENTITY_TABS', 'tabs');
assignConstant('ENTITY_TABLE', 'table');
assignConstant('ENTITY_TRACKER', 'tracker');
assignConstant('ENTITY_VALIDATOR', 'validator');
assignConstant('ENTITY_VALUE', 'value');
assignConstant('ENTITY_VIEW', 'view');
assignConstant('ENTITY_VIEW_BUILDER', 'view_builder');
assignConstant('ENTITY_VISITOR', 'visitor');
assignConstant('ENTITY_WEAVER', 'weaver');
assignConstant('ENTITY_YAML', 'yaml');
assignConstant('ENTITY_ZIP_ARCHIVE', 'zip_archive');

?>