<?php

namespace ExternalApi\Mobile;

use Cloud\Jwt;
use Cloud\UserService;
use Domain\User\Service\MobileAppUserService;
use ExternalApi\Exception\AuthorizationFailedException;
use ExternalApi\Exception\BlockedBecauseLicenseNumberReductionException;
use ExternalApi\Exception\DeviceBlockedException;
use ExternalApi\Exception\LicensesNumberExtendedException;
use ExternalApi\Exception\NoLicensesAvailableException;

class CreateJwtTokenAction extends Action
{
    private const JWT_TTL_MOBILE_CONFIG = 'jwt_ttl_mobile_seconds';
    private const JWT_TYPE = 'mobile';

    protected function doAction()
    {
        $ttl = $this->config[self::EAPI_CONFIG_KEY][self::JWT_TTL_MOBILE_CONFIG] ?: 0;
        $user = UserService::getUserById($this->userId);
        $jwt = new Jwt(\App::getInstance()->getConfig(), $user);
        $token = $jwt->generateToken([
            'ttl' => $ttl,
            'fields' => [
                'fullName',
                'id',
            ],
            'type' => self::JWT_TYPE,
        ]);

        try {
            $appType = $this->getAppType();
            $imei = $this->getImei();

            $logData = [
                'appType' => $appType,
                'userId' => $user->getId(),
                'token' => (string)$token,
                'imei' => $imei
            ];
            \App::getInstance()->appLogger->info('Użycie aplikacji mobilnej: ' . json_encode($logData));

            (new MobileAppUserService())->registerAppUsageByUser($appType, $user, $token, $imei);
            $jwt->markActiveJwtToken($user, 'key.activeJwtMobileToken', $ttl);
            $this->addMobileActivity(MobileAppUserService::ACTIVITY_REGENERATE_TOKEN, 'SUCCESS');
        } catch (LicensesNumberExtendedException $e) {
            return ['success' => false, 'message' => self::ERROR_LABEL_LICENSES_EXTENDED];
        } catch (NoLicensesAvailableException $e) {
            return ['success' => false, 'message' => self::ERROR_LABEL_NO_LICENSES_AVAILABLE];
        } catch (DeviceBlockedException $e) {
            return ['success' => false, 'message' => self::ERROR_LABEL_DEVICE_BLOCKED];
        } catch (BlockedBecauseLicenseNumberReductionException $e) {
            return ['success' => false, 'message' => self::ERROR_LABEL_BLOCKED_DUE_LICENSE_REDUCTION];
        } catch (\Exception $e) {
            $sentryId = \App::getInstance()->sentryCaptureException($e);
            return ['success' => false, 'message' => self::ERROR_LABEL_INTERNAL_ERROR, 'identifier' => $sentryId];
        }

        return [
            'token' => (string)$token,
            'features' => [
                'releaseLicense' => true,
                'syncCallAfterEnded' => true,
            ],
        ];
    }

    protected function handleException(\Exception $exception)
    {
        $classname = get_class($exception);
        $exceptionType = substr($classname, strrpos($classname, '\\') + 1);

        if ($exception instanceof AuthorizationFailedException) {
            return ['success' => false, 'message' => self::ERROR_LABEL_INVALID_AUTHORIZATION_DATA];
        }

        $this->addMobileActivity(MobileAppUserService::ACTIVITY_REGENERATE_TOKEN, $exceptionType);

        return parent::handleException($exception);
    }
}
