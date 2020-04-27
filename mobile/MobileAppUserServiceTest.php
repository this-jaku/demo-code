<?php

namespace Domain\User\DataTransferObject;

use Cloud\Licenses;
use Cloud\User;
use Domain\User\Service\MobileAppUserService;
use ExternalApi\Exception\BlockedBecauseLicenseNumberReductionException;
use ExternalApi\Exception\DeviceBlockedException;
use ExternalApi\Exception\LicensesNumberExtendedException;
use ExternalApi\Exception\NoLicensesAvailableException;
use Lcobucci\JWT\Token;
use PHPUnit\Framework\TestCase;

class MobileAppUserServiceTest extends TestCase
{
    private $appTypes = [
        \Crm_MobileAppActiveUsers::APP_TYPE_LITE => Licenses::LICENSE_APP_MOBILE_LITE,
        \Crm_MobileAppActiveUsers::APP_TYPE_FULL => Licenses::LICENSE_APP_MOBILE_FULL
    ];

    /** @var \Crm_MobileAppActiveUsers | \PHPUnit_Framework_MockObject_MockObject */
    private $mobileAppActiveUsersModelMock;

    /** @var Licenses | \PHPUnit_Framework_MockObject_MockObject */
    private $cloudLicensesMock;

    /** @var MobileAppUserService | \PHPUnit_Framework_MockObject_MockObject */
    private $mobileAppUserService;

    /** @var User */
    private $user;

    public function setUp()
    {
        $this->mobileAppActiveUsersModelMock = $this->getMockBuilder(\Crm_MobileAppActiveUsers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->cloudLicensesMock = $this->getMockBuilder(Licenses::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mobileAppUserService = new MobileAppUserService(
            $this->mobileAppActiveUsersModelMock,
            $this->cloudLicensesMock
        );

        $this->user = (new User())
            ->setId('1')
            ->setCustomersId('123');
    }

    public function dataProviderUserCorrectlyRegenerateToken()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //pierwsze logowanie/użycie
            $feed[] = [$appType, [$licenseKey => 10], 9, false];
            //ponowne logowanie
            $feed[] = [$appType, [$licenseKey => 10], 9, ['reason' => null, 'active' => true, 'imei' => '1234']];
            //licencja deaktywowana z pwoodu nieaktywności użytkownika, użytkownik wrócił do pracy po 30 danich, ale nadal są dostępne wolne licencje
            $feed[] = [
                $appType,
                [$licenseKey => 10],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_NO_ACTIVITY, 'active' => false, 'imei' => '1234']
            ];
            //licencja deaktywowana z innego powodu, ale nadal są dostępne wolne licencje
            $feed[] = [
                $appType,
                [$licenseKey => 10],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_LICENSE_DEACTIVATED, 'active' => false, 'imei' => '1234']
            ];
            //zmniejszono ilość licencji, użytkownik został wyrzucony jako nadmiarowy, ale obecnie ponownie dostępne są wolne licencje
            $feed[] = [
                $appType,
                [$licenseKey => 10],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_LICENSE_REDUCED, 'active' => false, 'imei' => '1234']
            ];
            //urządzenie zostało zablokowane (imei = 54695), ale obecny request idzie z innym imei (1234),
            // więc token powinien zostać wygenerowany dla nowego imei
            $feed[] = [
                $appType,
                [$licenseKey => 10],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_LICENSE_REDUCED, 'active' => false, 'imei' => '54695']
            ];
            // klient wcześniej się wylogował, ale teraz ponownie się zalogował i zabiera wolną licencję
            $feed[] = [
                $appType,
                [$licenseKey => 10],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_USER_LOGGED_OUT, 'active' => false, 'imei' => '1234']
            ];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderUserCorrectlyRegenerateToken
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testUserCorrectlyRegenerateToken($appType, $licenses, $usedLicences, $licenseUsedByUser)
    {
        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->mobileAppActiveUsersModelMock->expects($this->once())->method('registerAppUsageByUser');
        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }

    public function dataProviderTokenNotGenerateBecauseLicensesNumberWereReduced()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //zmniejszono ilość licencji, użytkownik został wyrzucony jako nadmiarowy, dostępne licencje są używane są przez innych użytkowników
            $feed[] = [
                $appType,
                [$licenseKey => 9],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_LICENSE_REDUCED, 'active' => false, 'imei' => '1234']
            ];
            $feed[] = [
                $appType,
                [$licenseKey => 9],
                10,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_LICENSE_REDUCED, 'active' => false, 'imei' => '1234']
            ];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderTokenNotGenerateBecauseLicensesNumberWereReduced
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testTokenNotGenerateBecauseLicensesNumberWereReduced(
        $appType,
        $licenses,
        $usedLicences,
        $licenseUsedByUser
    ) {
        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->expectException(BlockedBecauseLicenseNumberReductionException::class);

        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }

    public function dataProviderTokenNotGenerateBecauseDeviceWasBlocked()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //urządzenie zostało zablokowane.
            $feed[] = [
                $appType,
                [$licenseKey => 9],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_DEVICE_BLOCKED, 'active' => false, 'imei' => '1234']
            ];
            $feed[] = [
                $appType,
                [$licenseKey => 11],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_DEVICE_BLOCKED, 'active' => false, 'imei' => '1234']
            ];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderTokenNotGenerateBecauseDeviceWasBlocked
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testTokenNotGenerateBecauseDeviceWasBlocked($appType, $licenses, $usedLicences, $licenseUsedByUser)
    {

        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->expectException(DeviceBlockedException::class);
        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }

    public function dataProviderTokenNotGenerateBecauseUnknownReason()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //Nie wiadomo jak obsłużyć powód blokady. Blokuj bez względu na ilości licencji
            $feed[] = [
                $appType,
                [$licenseKey => 11],
                9,
                ['reason' => 'unknown reason', 'active' => false, 'imei' => '1234']
            ];
            $feed[] = [
                $appType,
                [$licenseKey => 11],
                9,
                ['reason' => 'unknown reason', 'active' => false, 'imei' => '1234']
            ];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderTokenNotGenerateBecauseUnknownReason
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testTokenNotGenerateBecauseUnknownReason($appType, $licenses, $usedLicences, $licenseUsedByUser)
    {
        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->expectException(\LogicException::class);
        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }

    public function dataProviderTokenNotGenerateBecauseReasonSetForActiveLicense()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //Nie powinno być powodu blokady, jeśli wpis licencji jest aktywny - bez względu na ilość licencji
            $feed[] = [
                $appType,
                [$licenseKey => 11],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_DEVICE_BLOCKED, 'active' => true, 'imei' => '1234']
            ];
            $feed[] = [
                $appType,
                [$licenseKey => 11],
                9,
                ['reason' => \Crm_MobileAppActiveUsers::REASON_DEVICE_BLOCKED, 'active' => true, 'imei' => '1234']
            ];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderTokenNotGenerateBecauseReasonSetForActiveLicense
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testTokenNotGenerateBecauseReasonSetForActiveLicense(
        $appType,
        $licenses,
        $usedLicences,
        $licenseUsedByUser
    ) {
        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->expectException(\LogicException::class);
        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }

    public function dataProviderTokenNotGenerateBecauseNoLicenseAvailableForNewUser()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //uzytkownik nie używa aplikacji, użytych 9/9 - nie ma dostępnych
            $feed[] = [$appType, [$licenseKey => 9], 9, false];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderTokenNotGenerateBecauseNoLicenseAvailableForNewUser
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testTokenNotGenerateBecauseNoLicenseAvailableForNewUser(
        $appType,
        $licenses,
        $usedLicences,
        $licenseUsedByUser
    ) {
        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->expectException(NoLicensesAvailableException::class);
        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }


    public function dataProviderTokenNotGenerateBecauseNoLicenseAvailableForAnyUser()
    {
        $feed = [];
        foreach ($this->appTypes as $appType => $licenseKey) {
            //uzytkownik nie używa aplikacji, użytych 9/8 - nie ma dostępnych
            $feed[] = [$appType, [$licenseKey => 8], 9, false];
            //uzytkownik używa aplikacji, użytych 9/8 - nie generuj, coś jest nie tak ze spójnością danych
            $feed[] = [$appType, [$licenseKey => 8], 9, ['reason' => null, 'active' => true, 'imei' => '1234']];
        }

        return $feed;
    }

    /**
     * @dataProvider dataProviderTokenNotGenerateBecauseNoLicenseAvailableForAnyUser
     * @param string $appType
     * @param array $licenses
     * @param int $usedLicences
     * @param array $licenseUsedByUser
     */
    public function testTokenNotGenerateBecauseNoLicenseAvailableForAnyUser(
        $appType,
        $licenses,
        $usedLicences,
        $licenseUsedByUser
    ) {
        $this->cloudLicensesMock->method('getLicensesByCustomerId')->willReturn($licenses);
        $this->mobileAppActiveUsersModelMock->method('getUsedMobileAppLicensesCount')->willReturn($usedLicences);
        $this->mobileAppActiveUsersModelMock->method('getLicenseUsedByUser')->willReturn($licenseUsedByUser);
        $this->expectException(LicensesNumberExtendedException::class);
        $this->mobileAppUserService->registerAppUsageByUser($appType, $this->user, new Token(), '1234');
    }
}
