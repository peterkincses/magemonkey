<?php

class Ebizmarts_MageMonkeyApi_ApiController extends Mage_Core_Controller_Front_Action {

    /**
     * Predispatch: Check for valid API KEY parameter.
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch() {
        parent::preDispatch();

        if ( !$this->getRequest()->isDispatched() )
            return;

        $action = $this->getRequest()->getActionName();
        $openActions = array(
            'activate',
        );
        $pattern = '/^(' . implode('|', $openActions) . ')/i';

        if ( !preg_match($pattern, $action) ) {

            //Check for valid api key.
            $requestApiKey = $this->getRequest()->getParam('api_key', '');
            $app = Mage::getResourceModel('monkeyapi/application_collection')
                ->setApiKeyFilter($requestApiKey)
                ->setOnlyEnabledApiKeyFilter()
                ->setActiveDeviceFilter()
                ->setPageSize(1)
                ->getFirstItem();

            if(!$app->getId()) {
                $this->_setClientError(400, 4004);
                return;
            }

        }

    }

    /**
     * Activate device action.
     */
    public function activateAction() {
        if($this->getRequest()->isPost()) {

            $postData = $this->_jsonPayload();

            if( false === $postData ) {
                $this->_setClientError(400, 4001);
                return;
            }

            if( !isset($postData->key) ) {
                $this->_setClientError(400, 4002);
                return;
            }

            $activationKey = $postData->key;

            $app = Mage::getResourceModel('monkeyapi/application_collection')->setKeyFilter($activationKey)
            ->setPageSize(1)
            ->getFirstItem();

            if( !$app->getId() or (1 === (int)$app->getActivated()) ) {
                $this->_setClientError(400, 4003);
                return;
            }

            $app->setActivated(1)->save();

            $this->_setSuccess(200, array('api_key' => $app->getApplicationRequestKey()));
            return;

        }
        else {
            $this->_setClientError(405, 4051);
            return;
        }
    }

    /**
     * Abandoned Carts statistics.
     */
    public function acstatsAction() {

        if( !$this->getRequest()->isGet() ) {
            $this->_setClientError(405, 4052);
            return;
        }

        $this->_setSuccess(200, array());
        return;

    }

    private function _jsonPayload() {
        $payload = $this->getRequest()->getRawBody();

        $data = json_decode($payload);

        if( !is_object($data) or empty($payload) ) {
            $data = false;
        }

        return $data;
    }

    private function _setSuccess($httpCode, $content) {
        return $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setBody(json_encode($content));
    }

    private function _setClientError($httpCode, $code) {
        return $this->getResponse()
            ->setHttpResponseCode($httpCode)
            ->setBody($this->_error($code));
    }

    private function _error($code) {

        $message = '';

        //@see config.xml
        $errors = Mage::getConfig()->getNode('global/monkeyapi_errorcodes')->children();

        foreach($errors as $_error) {
            if( ((int)$_error->code) == $code) {
                $message = (string)$_error->message;
                break;
            }
        }

        return json_encode(
            array('error_code' => $code,
            'error_message' => $message)
        );

    }


}