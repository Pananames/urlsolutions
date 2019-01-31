<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

spl_autoload_register(function ($class) {
    include ROOTDIR . '/modules/registrars/urlsolutions/lib/' . join('/', explode('\\', $class)) . '.php';
});

function urlsolutions_getConfigArray()
{
    return [
        'Description' => [
            'Type' => 'System',
            'Value' => 'Domain Registry Module v.2.0. More about API: <a href="https://docs.pananames.com/" target="_blank">docs.pananames.com</a>'
        ],

        'api_signature' => [
            'FriendlyName' => 'Signature',
            'Size' => '50',
            'Type' => 'text'
        ],

        'api_url' => [
            'FriendlyName' => 'URL',
            'Size' => '50',
            'Type' => 'text'
        ],

        'debug_logging' => [
            'FriendlyName' => 'Module Log',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Module Log debug messages',
        ],

        'load_pn_prices' => [
            'FriendlyName' => 'Enable PN Prices Loading',
            'Type' => 'yesno',
            'Description' => 'Should Pananames prices be used',
        ],

        'pn_price_tax' => [
            'FriendlyName' => 'Tax',
            'Size' => '3',
            'Type' => 'text',
            'Default' => 13,
            'Description' => 'Tax in percent which should be added to base price (integer value, example: 13 => 13%)'
        ],

        'pn_price_min_cost' => [
            'FriendlyName' => 'Min Cost',
            'Size' => '3',
            'Type' => 'text',
            'Default' => 0.3,
            'Description' => 'Min cost for domain with tax (in $)'
        ],

        'pn_price_margin' => [
            'FriendlyName' => 'Min Margin',
            'Size' => '3',
            'Type' => 'text',
            'Default' => 0.3,
            'Description' => 'Increase cost on price margin if finally cost less then this value (in $)'
        ]
    ];
}

function urlsolutions_AdminCustomButtonArray($params)
{
    $buttons = [];

    try {
        $status = (new \UrlSolutions\Whmcs())->getDomainStatus($params['domainid']);

        if ($status == 'Pending Transfer') {
            $buttons['GetTransferStatus'] = 'GetTransferStatus';
        } else {
            $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
            $domain = $domainApi->getInfo($params['domainname']);

            if (in_array($domain['status'], [\UrlSolutions\Domain::DOMAIN_TRANSFER_OUT_READY_STATUS, \UrlSolutions\Domain::DOMAIN_TRANSFERRING_OUT_STATUS])) {
                $buttons['Transfer Out Cancel'] = 'SaveRegistrarLock';
            } elseif (in_array($domain['status'], [\UrlSolutions\Domain::DOMAIN_OK_STATUS, \UrlSolutions\Domain::DOMAIN_EXPIRED_STATUS])) {
                $buttons['Transfer Out Init'] = 'TransferOutInit';
            } elseif ($domain['status'] == \UrlSolutions\Domain::DOMAIN_REDEMPTION_STATUS) {
                $buttons['Redeem'] = 'RedeemDomain';
            }

            $buttons['Resend Email'] = 'ResendVerificationEmail';
        }
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }

    return $buttons;
}

function urlsolutions_RegisterDomain($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);

        $nServers = [
            $params['ns1'],
            $params['ns2'],
            $params['ns3'],
            $params['ns4'],
            $params['ns5']
        ];

        $domainApi->register($params['domainname'], $params, $nServers);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_RenewDomain($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->renew($params['domainname'], $params['regperiod']);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_TransferDomain($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->transfer($params['domainname'], $params, $params['eppcode']);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_GetTransferStatus($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $status = $domainApi->getTransferStatus($params['domainname']);

        return [
            'success' => true,
            'message' => "Transfer Status: {$status['description']}."
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_TransferSync($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainName = $params['domainname'];

        $result = [];
        $status = $domainApi->getTransferStatus($domainName);

        if ($status['success']) {
            $info = $domainApi->getInfo($domainName);

            $result['completed'] = true;
            $result['expirydate'] = date('Y-m-d', strtotime($info['expiration_date']));
        } elseif ($status['rejected']) {
            $result['failed'] = true;
            $result['reason'] = "Possible reason: {$status['description']}.";
        }

        return $result;
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_GetNameservers($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        return $domainApi->getNameServers($params['domainname']);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_SaveNameservers($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domain = $params['domainname'];

        $result = $domainApi->getNameServers($domain);
        $oldNServers = implode(' ', $result);

        $nServers = [
            $params['ns1'],
            $params['ns2'],
            $params['ns3'],
            $params['ns4'],
            $params['ns5']
        ];

        $result = $domainApi->setNameServers($domain, $nServers);
        $newNServers = implode(' ', $result);

        \UrlSolutions\Whmcs::logChanges('NS changed from ['. $oldNServers .'] to ['. $newNServers .'] - Domain ID: ' . $params['domainid'] .' - Domain: ' . $params['domainname']);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_IDProtectToggle($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->setWhoisPrivacy($params['domainname'], $params['protectenable']);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_GetContactDetails($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);

        return $domainApi->getWhoisInfo($params['domainname']);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_SaveContactDetails($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainName = $params['domainname'];

        $whmcs = new \UrlSolutions\Whmcs();
        $userId = $whmcs->getDomainOwnerId($params['domainid']);

        $data = $params['contactdetails'];
        $data['userid'] = $userId;

        $domainApi->setWhoisInfo($domainName, $data);

        $settings = $whmcs->getDomainSettings($domainName);

        if ($settings['idprotection']) {
            $domainApi->enableWhoisPrivacy($domainName);
        }

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_TransferOutInit($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->initTransferOut($params['domainname']);

        $data = $domainApi->getWhoisInfo($params['domainname']);
        $email = $data['registrant']['Email'];

        return [
            'success' => true,
            'message' => "Domain has been unlocked and EPP code was sent to {$email}."
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_SaveRegistrarLock($params)
{
    try {
        $domain = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $info = $domain->getInfo($params['domainname']);

        if ($info['status'] == 'transfer out ready' && $info['lock_status'] == 'unlocked') {
            $domain->cancelTransferOut($params['domainname']);

            return [
                'success' => true,
            ];
        }

        return [
            'error' => 'Domain status: "' . $info['status'] . '", lock status: "' . $info['lock_status'] . '". Domain can\'t be locked.'
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_ResendVerificationEmail($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->resendVerificationEmail($params['domainname']);

        $data = $domainApi->getWhoisInfo($params['domainname']);
        $email = $data['registrant']['Email'];

        return [
            'success' => true,
            'message' => "Verification email has been sent to {$email}."
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_Sync($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $info = $domainApi->getInfo($params['domain']);

        if (empty($info)) {
            return ['error' => 'Can`t retreive domain info'];
        }

        $data = [];

        if (!empty($info['expiration_date']) && ($expiryDate = strtotime($info['expiration_date']))) {
            $data['expirydate'] = date('Y-m-d', $expiryDate);
        }

        if (in_array($info['status'], [\UrlSolutions\Domain::DOMAIN_EXPIRED_STATUS, \UrlSolutions\Domain::DOMAIN_REDEMPTION_STATUS])) {
            $data['expired'] = true;
        } elseif ($info['status'] == \UrlSolutions\Domain::DOMAIN_TRANSFERRING_OUT_STATUS) {
            $data['transferredAway'] = true;
        } elseif (in_array($info['status'], [\UrlSolutions\Domain::DOMAIN_OK_STATUS, \UrlSolutions\Domain::DOMAIN_SUSPENDED_STATUS, \UrlSolutions\Domain::DOMAIN_TRANSFER_OUT_READY_STATUS])) {
            $data['active'] = true;
        }

        return $data;
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_RequestDelete($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->requestDelete($params['domainname']);

        \UrlSolutions\Whmcs::logChanges('Domain Deleted Successfully - Domain ID: ' . $params['domainid'] .' - Domain: ' . $params['domainname']);

        return [
            'success' => true,
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_GetDomainInfo($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domain = $params['domainname'];

        $info = $domainApi->getInfo($domain);
        $prices = $domainApi->getPrices($domain);

        return array_merge($info, $prices);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_RedeemDomain($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $api = $domainApi->redeem($params['domainname']);

        if (!empty($api['HTTP code']) && $api['HTTP code'] == 200) {
            $info = $domainApi->getInfo($params['domainname']);

            if (!empty($info['status']) && $info['status'] == \UrlSolutions\Domain::DOMAIN_OK_STATUS) {
                update_query('tbldomains', ['status' => 'Active'], ['domain' => $params['domainname']]);

                \UrlSolutions\Whmcs::logChanges('Domain restored successfully - Domain ID: ' . $params['domainid'] . ' - Domain: ' . $params['domainname']);
            }
        }

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_GetPrivateNameserver($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);

        return $domainApi->getChildNameserver($params['domainname']);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_GetLockStatuses($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);

        return $domainApi->getRegistryStatusCodes($params['domainname']);
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_RegisterNameserver($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->createChildNameserver($params['domainname'], $params['nameserver'], $params['ipaddress'], $params['ip6address']);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_ModifyNameserver($params)
{
    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->updateChildNameserver($params['domainname'], $params['nameserver'], $params['ipaddress'], $params['ip6address']);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

function urlsolutions_DeleteNameserver($params)
{
    $host = substr($params['nameserver'], 0, strlen($params['nameserver']) - strlen($params['domainname']) - 1);

    try {
        $domainApi = new \UrlSolutions\Domain($params['api_url'], $params['api_signature']);
        $domainApi->deleteChildNameserver($params['domainname'], $host);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}
