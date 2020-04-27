<?php

namespace Domain\User\Service;

use Cloud\Licenses;
use Cloud\User;
use Domain\User\Transformer\Model\MobileAppTypeToLicenseKeyTransformer;
use ExternalApi\Exception\BlockedBecauseLicenseNumberReductionException;
use ExternalApi\Exception\DeviceBlockedException;
use ExternalApi\Exception\ExecuteException;
use ExternalApi\Exception\LicensesNumberExtendedException;
use ExternalApi\Exception\NoLicensesAvailableException;
use Lcobucci\JWT\Token;

class MobileAppUserService
{
    public const ACTIVITY_SYNC_CALL = 'SYNC_CALL';
    public const ACTIVITY_REGENERATE_TOKEN = 'REGENERATE_TOKEN';
    public const ACTIVITY_LOGUOT = 'LOGOUT';

    /** @var \Crm_MobileAppActiveUsers */
    private $mobileAppActiveUsersModel;

    /** @var Licenses */
    private $cloudLicenses;

    public function __construct(\Crm_MobileAppActiveUsers $mobileAppActiveUsersModel = null, Licenses $cloudLicenses = null)
    {
        $this->mobileAppActiveUsersModel = $mobileAppActiveUsersModel ?: new \Crm_MobileAppActiveUsers();
        $this->cloudLicenses = $cloudLicenses ?: new Licenses();
    }

    /**
     * @param string $appType
     * @param User $user
     * @param Token $token
     * @param string $imei
     * @throws BlockedBecauseLicenseNumberReductionException
     * @throws DeviceBlockedException
     * @throws ExecuteException
     * @throws LicensesNumberExtendedException
     * @throws NoLicensesAvailableException
     */
    public function registerAppUsageByUser($appType, User $user, Token $token, $imei): void
    {
        try {
            $licenseKey = (new MobileAppTypeToLicenseKeyTransformer())->transform($appType);
            $allowedLicences = $this->cloudLicenses->getLicensesByCustomerId($user->getCustomersId(), [$licenseKey]);
            $allowedLicences = (int)$allowedLicences[$licenseKey];
            $usedLicences = $this->mobileAppActiveUsersModel->getUsedMobileAppLicensesCount($appType, $user->getCustomersId());
            $licenseUsedByUser = $this->mobileAppActiveUsersModel->getLicenseUsedByUser($appType, $user->getId());
        } catch (\Exception $e) {
            \App::getInstance()->sentryCaptureException($e);
            throw new ExecuteException(tr('Błąd pobrania informacji o licencjach aplikacji mobilnej'), 0, $e);
        }

        if ($licenseUsedByUser && $licenseUsedByUser['active'] === false) {
            if ($licenseUsedByUser['reason'] === \Crm_MobileAppActiveUsers::REASON_LICENSE_REDUCED && $usedLicences >= $allowedLicences) {
                throw new BlockedBecauseLicenseNumberReductionException();
            }

            if ($licenseUsedByUser['reason'] === \Crm_MobileAppActiveUsers::REASON_DEVICE_BLOCKED && $licenseUsedByUser['imei'] === $imei) {
                throw new DeviceBlockedException();
            }
        }

        $knownReasons = [
            \Crm_MobileAppActiveUsers::REASON_NO_ACTIVITY,
            \Crm_MobileAppActiveUsers::REASON_LICENSE_DEACTIVATED,
            \Crm_MobileAppActiveUsers::REASON_LICENSE_REDUCED,
            \Crm_MobileAppActiveUsers::REASON_DEVICE_BLOCKED,
            \Crm_MobileAppActiveUsers::REASON_USER_LOGGED_OUT,
        ];

        if ($licenseUsedByUser && $licenseUsedByUser['active'] === false && !in_array($licenseUsedByUser['reason'], $knownReasons)) {
            throw new \LogicException(
                sprintf(
                    'Nieznana definicja powodu deaktywacli licencji mobilnej, reason: %s, userId: %s',
                    $licenseUsedByUser['reason'],
                    $user->getId()
                )
            );
        }

        if ($licenseUsedByUser && $licenseUsedByUser['active'] === true && !empty($licenseUsedByUser['reason'])) {
            throw new \LogicException(
                sprintf(
                    'Aktywna licencja posiada powod deaktywacji, reason: %s, userId: %s',
                    $licenseUsedByUser['reason'],
                    $user->getId()
                )
            );
        }

        if ($usedLicences > $allowedLicences) {
            throw new LicensesNumberExtendedException();
        }

        if ($usedLicences === $allowedLicences && $licenseUsedByUser === false) {
            throw new NoLicensesAvailableException();
        }

        try {
            $this->mobileAppActiveUsersModel->registerAppUsageByUser($appType, $user->getId(), $user->getCustomersId(), (string)$token, $imei);
        } catch (\Exception $e) {
            throw new ExecuteException(tr('Błąd zapisu informacji o użyciu przez użytkownika aplikacji mobilnej'), 0, $e);
        }
    }

    public function deactivateNonActiveUsers(): void
    {
        $this->mobileAppActiveUsersModel->deactivateNonActiveUsers();
    }

    public function unregisterAppUsageByUser(string $appType, int $userId, string $imei): void
    {
        $this->mobileAppActiveUsersModel->unregisterAppUsageByUser($appType, $userId, $imei);
    }
}
