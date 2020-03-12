<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\FunctionalTestingFramework\Module;

use Codeception\Module\WebDriver;
use Codeception\Test\Descriptor;
use Codeception\TestInterface;
use Magento\FunctionalTestingFramework\Allure\AllureHelper;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Util\Uri;
use Magento\FunctionalTestingFramework\DataGenerator\Handlers\CredentialStore;
use Magento\FunctionalTestingFramework\DataGenerator\Persist\Curl\WebapiExecutor;
use Magento\FunctionalTestingFramework\Util\Protocol\CurlTransport;
use Magento\FunctionalTestingFramework\Util\Protocol\CurlInterface;
use Magento\FunctionalTestingFramework\Util\ConfigSanitizerUtil;
use Yandex\Allure\Adapter\AllureException;
use Yandex\Allure\Adapter\Support\AttachmentSupport;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;

/**
 * MagentoWebDriver module provides common Magento web actions through Selenium WebDriver.
 *
 * Configuration:
 *
 * ```
 * modules:
 *     enabled:
 *         - \Magento\FunctionalTestingFramework\Module\MagentoWebDriver
 *     config:
 *         \Magento\FunctionalTestingFramework\Module\MagentoWebDriver:
 *             url: magento_base_url
 *             backend_name: magento_backend_name
 *             username: admin_username
 *             password: admin_password
 *             browser: chrome
 * ```
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MagentoWebDriver extends WebDriver
{
    use AttachmentSupport;

    /**
     * List of known magento loading masks by selector
     * @var array
     */
    public static $loadingMasksLocators = [
        '//div[contains(@class, "loading-mask")]',
        '//div[contains(@class, "admin_data-grid-loading-mask")]',
        '//div[contains(@class, "admin__data-grid-loading-mask")]',
        '//div[contains(@class, "admin__form-loading-mask")]',
        '//div[@data-role="spinner"]'
    ];

    /**
     * The module required fields, to be set in the suite .yml configuration file.
     *
     * @var array
     */
    protected $requiredFields = [
        'url',
        'backend_name',
        'username',
        'password',
        'browser'
    ];

    /**
     * Set all Locale variables to NULL.
     *
     * @var array $localeAll
     */
    protected static $localeAll = [
        LC_COLLATE => null,
        LC_CTYPE => null,
        LC_MONETARY => null,
        LC_NUMERIC => null,
        LC_TIME => null,
        LC_MESSAGES => null,
    ];

    /**
     * Current Test Interface
     *
     * @var TestInterface
     */
    private $current_test;

    /**
     * Png image filepath for current test
     *
     * @var string
     */
    private $pngReport;

    /**
     * Html filepath for current test
     *
     * @var string
     */
    private $htmlReport;

    /**
     * Array to store Javascript errors
     *
     * @var string[]
     */
    private $jsErrors = [];

    /**
     * Sanitizes config, then initializes using parent.
     * @return void
     */
    public function _initialize()
    {
        $this->config = ConfigSanitizerUtil::sanitizeWebDriverConfig($this->config);
        parent::_initialize();
        $this->cleanJsError();
    }

    /**
     * Calls parent reset, then re-sanitizes config
     *
     * @return void
     */
    public function _resetConfig()
    {
        parent::_resetConfig();
        $this->config = ConfigSanitizerUtil::sanitizeWebDriverConfig($this->config);
        $this->cleanJsError();
    }

    /**
     * Remap parent::_after, called in TestContextExtension
     * @param TestInterface $test
     * @return void
     */
    public function _runAfter(TestInterface $test)
    {
        parent::_after($test); // TODO: Change the autogenerated stub
    }

    /**
     * Override parent::_after to do nothing.
     * @return void
     * @param TestInterface $test
     * @SuppressWarnings(PHPMD)
     */
    public function _after(TestInterface $test)
    {
        // DO NOT RESET SESSIONS
    }

    /**
     * Returns URL of a host.
     *
     * @api
     * @return mixed
     * @throws ModuleConfigException
     */
    public function _getUrl()
    {
        if (!isset($this->config['url'])) {
            throw new ModuleConfigException(
                __CLASS__,
                "Module connection failure. The URL for client can't bre retrieved"
            );
        }
        return $this->config['url'];
    }

    /**
     * Uri of currently opened page.
     *
     * @return string
     * @api
     * @throws ModuleException
     */
    public function _getCurrentUri()
    {
        $url = $this->webDriver->getCurrentURL();
        if ($url == 'about:blank') {
            throw new ModuleException($this, 'Current url is blank, no page was opened');
        }
        return Uri::retrieveUri($url);
    }

    /**
     * Assert that the current webdriver url does not equal the expected string.
     *
     * @param string $url
     * @return void
     * @throws AllureException
     */
    public function dontSeeCurrentUrlEquals($url)
    {
        $actualUrl = $this->webDriver->getCurrentURL();
        $comparison = "Expected: $url\nActual: $actualUrl";
        AllureHelper::addAttachmentToCurrentStep($comparison, 'Comparison');
        $this->assertNotEquals($url, $actualUrl);
    }

    /**
     * Assert that the current webdriver url does not match the expected regex.
     *
     * @param string $regex
     * @return void
     * @throws AllureException
     */
    public function dontSeeCurrentUrlMatches($regex)
    {
        $actualUrl = $this->webDriver->getCurrentURL();
        $comparison = "Expected: $regex\nActual: $actualUrl";
        AllureHelper::addAttachmentToCurrentStep($comparison, 'Comparison');
        $this->assertNotRegExp($regex, $actualUrl);
    }

    /**
     * Assert that the current webdriver url does not contain the expected string.
     *
     * @param string $needle
     * @return void
     * @throws AllureException
     */
    public function dontSeeInCurrentUrl($needle)
    {
        $actualUrl = $this->webDriver->getCurrentURL();
        $comparison = "Expected: $needle\nActual: $actualUrl";
        AllureHelper::addAttachmentToCurrentStep($comparison, 'Comparison');
        $this->assertNotContains($needle, $actualUrl);
    }

    /**
     * Return the current webdriver url or return the first matching capture group.
     *
     * @param string|null $regex
     * @return string
     */
    public function grabFromCurrentUrl($regex = null)
    {
        $fullUrl = $this->webDriver->getCurrentURL();
        if (!$regex) {
            return $fullUrl;
        }
        $matches = [];
        $res = preg_match($regex, $fullUrl, $matches);
        if (!$res) {
            $this->fail("Couldn't match $regex in " . $fullUrl);
        }
        if (!isset($matches[1])) {
            $this->fail("Nothing to grab. A regex parameter with a capture group is required. Ex: '/(foo)(bar)/'");
        }
        return $matches[1];
    }

    /**
     * Assert that the current webdriver url equals the expected string.
     *
     * @param string $url
     * @return void
     * @throws AllureException
     */
    public function seeCurrentUrlEquals($url)
    {
        $actualUrl = $this->webDriver->getCurrentURL();
        $comparison = "Expected: $url\nActual: $actualUrl";
        AllureHelper::addAttachmentToCurrentStep($comparison, 'Comparison');
        $this->assertEquals($url, $actualUrl);
    }

    /**
     * Assert that the current webdriver url matches the expected regex.
     *
     * @param string $regex
     * @return void
     * @throws AllureException
     */
    public function seeCurrentUrlMatches($regex)
    {
        $actualUrl = $this->webDriver->getCurrentURL();
        $comparison = "Expected: $regex\nActual: $actualUrl";
        AllureHelper::addAttachmentToCurrentStep($comparison, 'Comparison');
        $this->assertRegExp($regex, $actualUrl);
    }

    /**
     * Assert that the current webdriver url contains the expected string.
     *
     * @param string $needle
     * @return void
     * @throws AllureException
     */
    public function seeInCurrentUrl($needle)
    {
        $actualUrl = $this->webDriver->getCurrentURL();
        $comparison = "Expected: $needle\nActual: $actualUrl";
        AllureHelper::addAttachmentToCurrentStep($comparison, 'Comparison');
        $this->assertContains($needle, $actualUrl);
    }

    /**
     * Close admin notification popup windows.
     *
     * @return void
     */
    public function closeAdminNotification()
    {
        // Cheating here for the minute. Still working on the best method to deal with this issue.
        try {
            $this->executeJS("jQuery('.modal-popup').remove(); jQuery('.modals-overlay').remove();");
        } catch (\Exception $e) {
        }
    }

    /**
     * Search for and Select multiple options from a Magento Multi-Select drop down menu.
     * e.g. The drop down menu you use to assign Products to Categories.
     *
     * @param string  $select
     * @param array   $options
     * @param boolean $requireAction
     * @throws \Exception
     * @return void
     */
    public function searchAndMultiSelectOption($select, array $options, $requireAction = false)
    {
        $selectDropdown     = $select . ' .action-select.admin__action-multiselect';
        $selectSearchText   = $select
            . ' .admin__action-multiselect-search-wrap>input[data-role="advanced-select-text"]';
        $selectSearchResult = $select . ' .admin__action-multiselect-label>span';

        $this->waitForPageLoad();
        $this->waitForElementVisible($selectDropdown);
        $this->click($selectDropdown);

        $this->selectMultipleOptions($selectSearchText, $selectSearchResult, $options);

        if ($requireAction) {
            $selectAction = $select . ' button[class=action-default]';
            $this->waitForPageLoad();
            $this->click($selectAction);
        }
    }

    /**
     * Select multiple options from a drop down using a filter and text field to narrow results.
     *
     * @param string   $selectSearchTextField
     * @param string   $selectSearchResult
     * @param string[] $options
     * @throws \Exception
     * @return void
     */
    public function selectMultipleOptions($selectSearchTextField, $selectSearchResult, array $options)
    {
        foreach ($options as $option) {
            $this->waitForPageLoad();
            $this->fillField($selectSearchTextField, '');
            $this->waitForPageLoad();
            $this->fillField($selectSearchTextField, $option);
            $this->waitForPageLoad();
            $this->click($selectSearchResult);
        }
    }

    /**
     * Wait for all Ajax calls to finish.
     *
     * @param integer $timeout
     * @return void
     */
    public function waitForAjaxLoad($timeout = null)
    {
        $timeout = $timeout ?? $this->_getConfig()['pageload_timeout'];

        try {
            $this->waitForJS('return !!window.jQuery && window.jQuery.active == 0;', $timeout);
        } catch (\Exception $exceptione) {
            $this->debug("js never executed, performing {$timeout} second wait.");
            $this->wait($timeout);
        }
        $this->wait(1);
    }

    /**
     * Wait for all JavaScript to finish executing.
     *
     * @param integer $timeout
     * @throws \Exception
     * @return void
     */
    public function waitForPageLoad($timeout = null)
    {
        $timeout = $timeout ?? $this->_getConfig()['pageload_timeout'];

        $this->waitForJS('return document.readyState == "complete"', $timeout);
        $this->waitForAjaxLoad($timeout);
        $this->waitForLoadingMaskToDisappear($timeout);
    }

    /**
     * Wait for all visible loading masks to disappear. Gets all elements by mask selector, then loops over them.
     *
     * @param integer $timeout
     * @throws \Exception
     * @return void
     */
    public function waitForLoadingMaskToDisappear($timeout = null)
    {
        foreach (self::$loadingMasksLocators as $maskLocator) {
            // Get count of elements found for looping.
            // Elements are NOT useful for interaction, as they cannot be fed to codeception actions.
            $loadingMaskElements = $this->_findElements($maskLocator);
            for ($i = 1; $i <= count($loadingMaskElements); $i++) {
                // Formatting and looping on i as we can't interact elements returned above
                // eg.  (//div[@data-role="spinner"])[1]
                $this->waitForElementNotVisible("({$maskLocator})[{$i}]", $timeout);
            }
        }
    }

    /**
     * @param float  $money
     * @param string $locale
     * @return array
     */
    public function formatMoney(float $money, $locale = 'en_US.UTF-8')
    {
        $this->mSetLocale(LC_MONETARY, $locale);
        $money = money_format('%.2n', $money);
        $this->mResetLocale();
        $prefix = substr($money, 0, 1);
        $number = substr($money, 1);
        return ['prefix' => $prefix, 'number' => $number];
    }

    /**
     * Parse float number with thousands_sep.
     *
     * @param string $floatString
     * @return float
     */
    public function parseFloat($floatString)
    {
        $floatString = str_replace(',', '', $floatString);
        return floatval($floatString);
    }

    /**
     * @param integer $category
     * @param string  $locale
     * @return void
     */
    public function mSetLocale(int $category, $locale)
    {
        if (self::$localeAll[$category] == $locale) {
            return;
        }
        foreach (self::$localeAll as $c => $l) {
            self::$localeAll[$c] = setlocale($c, 0);
        }
        setlocale($category, $locale);
    }

    /**
     * Reset Locale setting.
     * @return void
     */
    public function mResetLocale()
    {
        foreach (self::$localeAll as $c => $l) {
            if ($l !== null) {
                setlocale($c, $l);
                self::$localeAll[$c] = null;
            }
        }
    }

    /**
     * Scroll to the Top of the Page.
     * @return void
     */
    public function scrollToTopOfPage()
    {
        $this->executeJS('window.scrollTo(0,0);');
    }

    /**
     * Takes given $command and executes it against exposed MTF CLI entry point. Returns response from server.
     * @param string $command
     * @param string $arguments
     * @throws TestFrameworkException
     * @return string
     */
    public function magentoCLI($command, $arguments = null)
    {
        // Remove index.php if it's present in url
        $baseUrl = rtrim(
            str_replace('index.php', '', rtrim($this->config['url'], '/')),
            '/'
        );
        $apiURL = $baseUrl . '/' . ltrim(getenv('MAGENTO_CLI_COMMAND_PATH'), '/');

        $restExecutor = new WebapiExecutor();
        $executor = new CurlTransport();
        $executor->write(
            $apiURL,
            [
                'token' => $restExecutor->getAuthToken(),
                getenv('MAGENTO_CLI_COMMAND_PARAMETER') => $command,
                'arguments' => $arguments
            ],
            CurlInterface::POST,
            []
        );
        $response = $executor->read();
        $restExecutor->close();
        $executor->close();
        return $response;
    }

    /**
     * Runs DELETE request to delete a Magento entity against the url given.
     * @param string $url
     * @throws TestFrameworkException
     * @return string
     */
    public function deleteEntityByUrl($url)
    {
        $executor = new WebapiExecutor(null);
        $executor->write($url, [], CurlInterface::DELETE, []);
        $response = $executor->read();
        $executor->close();
        return $response;
    }

    /**
     * Conditional click for an area that should be visible
     *
     * @param string  $selector
     * @param string  $dependentSelector
     * @param boolean $visible
     * @throws \Exception
     * @return void
     */
    public function conditionalClick($selector, $dependentSelector, $visible)
    {
        $el = $this->_findElements($dependentSelector);
        if (sizeof($el) > 1) {
            throw new \Exception("more than one element matches selector " . $dependentSelector);
        }

        $clickCondition = null;
        if ($visible) {
            $clickCondition = !empty($el) && $el[0]->isDisplayed();
        } else {
            $clickCondition = empty($el) || !$el[0]->isDisplayed();
        }

        if ($clickCondition) {
            $this->click($selector);
        }
    }

    /**
     * Clear the given Text Field or Textarea
     *
     * @param string $selector
     * @return void
     */
    public function clearField($selector)
    {
        $this->fillField($selector, "");
    }

    /**
     * Assert that an element contains a given value for the specific attribute.
     *
     * @param string $selector
     * @param string $attribute
     * @param string $value
     * @return void
     */
    public function assertElementContainsAttribute($selector, $attribute, $value)
    {
        $attributes = $this->grabAttributeFrom($selector, $attribute);

        if (isset($value) && empty($value)) {
            // If an "attribute" is blank, "", or null we need to be able to assert that it's present.
            // When an "attribute" is blank or null it returns "true" so we assert that "true" is present.
            $this->assertEquals($attributes, 'true');
        } else {
            $this->assertContains($value, $attributes);
        }
    }

    /**
     * Sets current test to the given test, and resets test failure artifacts to null
     * @param TestInterface $test
     * @return void
     */
    public function _before(TestInterface $test)
    {
        $this->current_test = $test;
        $this->htmlReport = null;
        $this->pngReport = null;

        parent::_before($test);
    }

    /**
     * Override for codeception's default dragAndDrop to include offset options.
     * @param string  $source
     * @param string  $target
     * @param integer $xOffset
     * @param integer $yOffset
     * @return void
     */
    public function dragAndDrop($source, $target, $xOffset = null, $yOffset = null)
    {
        if ($xOffset !== null || $yOffset !== null) {
            $snodes = $this->matchFirstOrFail($this->baseElement, $source);
            $tnodes = $this->matchFirstOrFail($this->baseElement, $target);

            $targetX = intval($tnodes->getLocation()->getX() + $xOffset);
            $targetY = intval($tnodes->getLocation()->getY() + $yOffset);

            $travelX = intval($targetX - $snodes->getLocation()->getX());
            $travelY = intval($targetY - $snodes->getLocation()->getY());

            $action = new WebDriverActions($this->webDriver);
            $action->moveToElement($snodes)->perform();
            $action->clickAndHold($snodes)->perform();
            $action->moveByOffset($travelX, $travelY)->perform();
            $action->release()->perform();
        } else {
            parent::dragAndDrop($source, $target);
        }
    }

    /**
     * Function used to fill sensitive credentials with user data, data is decrypted immediately prior to fill to avoid
     * exposure in console or log.
     *
     * @param string $field
     * @param string $value
     * @return void
     * @throws TestFrameworkException
     */
    public function fillSecretField($field, $value)
    {
        // to protect any secrets from being printed to console the values are executed only at the webdriver level as a
        // decrypted value

        $decryptedValue = CredentialStore::getInstance()->decryptSecretValue($value);
        $this->fillField($field, $decryptedValue);
    }

    /**
     * Function used to create data that contains sensitive credentials in a <createData> <field> override.
     * The data is decrypted immediately prior to data creation to avoid exposure in console or log.
     *
     * @param string $command
     * @param null   $arguments
     * @throws TestFrameworkException
     * @return string
     */
    public function magentoCLISecret($command, $arguments = null)
    {
        // to protect any secrets from being printed to console the values are executed only at the webdriver level as a
        // decrypted value

        $decryptedCommand = CredentialStore::getInstance()->decryptAllSecretsInString($command);
        return $this->magentoCLI($decryptedCommand, $arguments);
    }

    /**
     * Override for _failed method in Codeception method. Adds png and html attachments to allure report
     * following parent execution of test failure processing.
     *
     * @param TestInterface $test
     * @param \Exception    $fail
     * @return void
     */
    public function _failed(TestInterface $test, $fail)
    {
        $this->debugWebDriverLogs($test);

        if ($this->pngReport === null && $this->htmlReport === null) {
            $this->saveScreenshot();
        }

        if ($this->current_test == null) {
            throw new \RuntimeException("Suite condition failure: \n" . $fail->getMessage());
        }

        $this->addAttachment($this->pngReport, $test->getMetadata()->getName() . '.png', 'image/png');
        $this->addAttachment($this->htmlReport, $test->getMetadata()->getName() . '.html', 'text/html');

        $this->debug("Failure due to : {$fail->getMessage()}");
        $this->debug("Screenshot saved to {$this->pngReport}");
        $this->debug("Html saved to {$this->htmlReport}");
    }

    /**
     * Function which saves a screenshot of the current stat of the browser
     * @return void
     */
    public function saveScreenshot()
    {
        $testDescription = "unknown." . uniqid();
        if ($this->current_test != null) {
            $testDescription = Descriptor::getTestSignature($this->current_test);
        }

        $filename = preg_replace('~\W~', '.', $testDescription);
        $outputDir = codecept_output_dir();
        $this->_saveScreenshot($this->pngReport = $outputDir . mb_strcut($filename, 0, 245, 'utf-8') . '.fail.png');
        $this->_savePageSource($this->htmlReport = $outputDir . mb_strcut($filename, 0, 244, 'utf-8') . '.fail.html');
    }

    /**
     * Go to a page and wait for ajax requests to finish
     *
     * @param string $page
     * @throws \Exception
     * @return void
     */
    public function amOnPage($page)
    {
        parent::amOnPage($page);
        $this->waitForPageLoad();
    }

    /**
     * Turn Readiness check on or off
     *
     * @param boolean $check
     * @throws \Exception
     * @return void
     */
    public function skipReadinessCheck($check)
    {
        $this->config['skipReadiness'] = $check;
    }

    /**
     * Clean Javascript errors in internal array
     *
     * @return void
     */
    public function cleanJsError()
    {
        $this->jsErrors = [];
    }

    /**
     * Save Javascript error message to internal array
     *
     * @param string $errMsg
     * @return void
     */
    public function setJsError($errMsg)
    {
        $this->jsErrors[] = $errMsg;
    }

    /**
     * Get all Javascript errors
     *
     * @return string
     */
    private function getJsErrors()
    {
        $errors = '';

        if (!empty($this->jsErrors)) {
            $errors = 'Errors in JavaScript:';
            foreach ($this->jsErrors as $jsError) {
                $errors .= "\n" . $jsError;
            }
        }
        return $errors;
    }

    /**
     * Verify that there is no JavaScript error in browser logs
     *
     * @return void
     */
    public function dontSeeJsError()
    {
        $this->assertEmpty($this->jsErrors, $this->getJsErrors());
    }

    /**
     * Takes a screenshot of the current window and saves it to `tests/_output/debug`.
     *
     * This function is copied over from the original Codeception WebDriver so that we still have visibility of
     * the screenshot filename to be passed to the AllureHelper.
     *
     * @param string $name
     * @return void
     * @throws AllureException
     */
    public function makeScreenshot($name = null)
    {
        if (empty($name)) {
            $name = uniqid(date("Y-m-d_H-i-s_"));
        }
        $debugDir = codecept_log_dir() . 'debug';
        if (!is_dir($debugDir)) {
            mkdir($debugDir, 0777);
        }
        $screenName = $debugDir . DIRECTORY_SEPARATOR . $name . '.png';
        $this->_saveScreenshot($screenName);
        $this->debug("Screenshot saved to $screenName");
        AllureHelper::addAttachmentToCurrentStep($screenName, 'Screenshot');
    }
}