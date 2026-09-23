<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Route as SymfonyRoute;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * What every functional test here needs: this extension and the vault it depends on.
 *
 * powermail and powermail_cond are deliberately absent. Their integrations are registered only
 * when they are installed, so the extension has to boot without them — and a suite that proves
 * that is worth more than one that needs them.
 */
abstract class AbstractJevTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['backend', 'install'];

    protected array $testExtensionsToLoad = ['nr_vault', 'webcon_jev'];

    /**
     * A row as it is in the database — deleted and hidden ones included, which is what a test
     * about deleting has to see. Connection::select() would apply the default restrictions.
     *
     * @return array<string, mixed>
     */
    protected function row(string $table, int $uid): array
    {
        $query = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();
        $row = $query
            ->select('*')
            ->from($table)
            ->where($query->expr()->eq('uid', $query->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($row, sprintf('%s row %d exists', $table, $uid));

        return $row;
    }

    /**
     * An administrator, signed in, with the language service a backend request would have.
     */
    protected function signInAsAdministrator(): BackendUserAuthentication
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users_admin.csv');
        $user = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($user);

        return $user;
    }

    /**
     * A request to one of the extension's backend routes, as the route dispatcher would pass it
     * to the controller: with the route, the module it belongs to, and a JSON body when given.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $json
     */
    protected function backendRequest(string $routeIdentifier, array $query = [], ?array $json = null): ServerRequestInterface
    {
        $route = $this->get(Router::class)->getRoute($routeIdentifier);
        self::assertInstanceOf(SymfonyRoute::class, $route, sprintf('route "%s" is registered', $routeIdentifier));

        $body = new Stream('php://temp', 'wb+');
        if ($json !== null) {
            $body->write(json_encode($json, JSON_THROW_ON_ERROR));
            $body->rewind();
        }

        $request = new ServerRequest(
            'https://typo3-testing.local/typo3' . $route->getPath(),
            $json === null ? 'GET' : 'POST',
            $body,
            $json === null ? [] : ['Content-Type' => 'application/json'],
        );
        $request = $request
            ->withQueryParams($query)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', $route);

        $module = $route->getOption('module');
        if ($module instanceof ModuleInterface) {
            $request = $request->withAttribute('module', $module);
        }

        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function decodeJson(string $body): array
    {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $map = [];
        foreach ($decoded as $key => $value) {
            $map[(string)$key] = $value;
        }

        return $map;
    }
}
