<?php

namespace Core\Interfaces;

class RouteRequestInterface {
    public ?string $DOCUMENT_ROOT;
    public ?string $REMOTE_ADDR;
    public ?string $REMOTE_PORT;
    public ?string $SERVER_SOFTWARE;
    public ?string $SERVER_PROTOCOL;
    public ?string $SERVER_NAME;
    public ?string $SERVER_PORT;
    public ?string $REQUEST_URI;
    public ?string $REQUEST_METHOD;
    public ?string $SCRIPT_NAME;
    public ?string $SCRIPT_FILENAME;
    public ?string $PATH_INFO;
    public ?string $PHP_SELF;
    public ?string $QUERY_STRING;
    public ?string $HTTP_HOST;
    public ?string $HTTP_CONNECTION;
    public ?string $HTTP_CACHE_CONTROL;
    public ?string $HTTP_SEC_CH_UA;
    public ?string $HTTP_SEC_CH_UA_MOBILE;
    public ?string $HTTP_SEC_CH_UA_PLATFORM;
    public ?string $HTTP_UPGRADE_INSECURE_REQUESTS;
    public ?string $HTTP_USER_AGENT;
    public ?string $HTTP_ACCEPT;
    public ?string $HTTP_SEC_FETCH_SITE;
    public ?string $HTTP_SEC_FETCH_MODE;
    public ?string $HTTP_SEC_FETCH_USER;
    public ?string $HTTP_SEC_FETCH_DEST;
    public ?string $HTTP_ACCEPT_ENCODING;
    public ?string $HTTP_ACCEPT_LANGUAGE;
    public ?string $HTTP_COOKIE;
    public ?string $REQUEST_TIME_FLOAT;
    public ?string $REQUEST_TIME;
    public ?object $GET;
    public ?object $PUT;
    public ?object $POST;
    public ?object $FILES;

    public function __construct(object $binding_data) {
        foreach ($binding_data as $k => $v) {
            $this->$k = $binding_data->$k  ? $binding_data->$k : null;
        }
    }
}