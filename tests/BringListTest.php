<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/ModuleStrictStubs.php';
include_once __DIR__ . '/../Bring List/module.php';

use PHPUnit\Framework\TestCase;

// These runtime status constants are not provided by the pinned Symcon stubs.
if (!defined('IS_CREATING')) {
    define('IS_CREATING', 100);
}
if (!defined('IS_ACTIVE')) {
    define('IS_ACTIVE', 102);
}

class BringListTest extends TestCase
{
    public function testOpeningFormLoadsListsWithoutApplyChanges(): void
    {
        $Module = new BringListFormFixture();
        $Lists = [['listUuid' => 'test-list', 'name' => 'Shopping']];
        $Module->Response = serialize(['lists' => $Lists]);

        $Form = json_decode($Module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([['caption' => 'Shopping', 'value' => 'test-list']], $Form['elements'][0]['items'][0]['options']);
        $this->assertSame($Lists, $Module->Lists);
        $this->assertSame(1, $Module->Requests);
    }

    public function testTemporaryFailurePreservesCachedLists(): void
    {
        $Module = new BringListFormFixture();
        $Module->Lists = [['listUuid' => 'test-list', 'name' => 'Shopping']];
        foreach ([serialize(false), serialize(null), serialize(['error' => 'unavailable'])] as $Response) {
            $Module->Response = $Response;
            $Form = json_decode($Module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([['caption' => 'Shopping', 'value' => 'test-list']], $Form['elements'][0]['items'][0]['options']);
            $this->assertCount(1, $Module->Lists);
        }
    }

    public function testSuccessfulEmptyResponseClearsRemovedLists(): void
    {
        $Module = new BringListFormFixture();
        $Module->Lists = [['listUuid' => 'removed-list', 'name' => 'Old list']];
        $Module->Response = serialize(['lists' => []]);
        $Form = json_decode($Module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $Form['elements'][0]['items'][0]['options']);
        $this->assertSame([], $Module->Lists);
    }

    public function testInactiveGatewayUsesCacheWithoutRequest(): void
    {
        $Module = new BringListFormFixture();
        $Module->Active = false;
        $Module->Lists = [['listUuid' => 'test-list', 'name' => 'Shopping']];
        $Form = json_decode($Module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $Form['elements'][0]['items'][0]['options']);
        $this->assertSame(0, $Module->Requests);
    }

    public function testUnavailableConfiguredListRemainsSelectable(): void
    {
        $Module = new BringListFormFixture();
        $Module->SelectedList = 'configured-list';
        foreach ([serialize(false), serialize(['lists' => []])] as $Response) {
            $Module->Response = $Response;
            $Form = json_decode($Module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
            $Options = $Form['elements'][0]['items'][0]['options'];
            $this->assertSame(['configured-list'], array_column($Options, 'value'));
            $this->assertStringContainsString('currently unavailable', $Options[0]['caption']);
            $this->assertSame([], $Module->Lists);
        }
    }

    public function testAvailableConfiguredListIsNotDuplicated(): void
    {
        $Module = new BringListFormFixture();
        $Module->SelectedList = 'test-list';
        $Module->Response = serialize(['lists' => [['listUuid' => 'test-list', 'name' => 'Shopping']]]);
        $Form = json_decode($Module->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([['caption' => 'Shopping', 'value' => 'test-list']], $Form['elements'][0]['items'][0]['options']);
    }
}

class BringListFormFixture extends BringList
{
    public array $Lists = [];
    public string $Response = '';
    public bool $Active = true;
    public int $Requests = 0;
    public string $SelectedList = '';
    private bool $ParentRegistered = false;

    public function __construct()
    {
    }

    protected function GetStatus(): int
    {
        return IS_ACTIVE;
    }

    public function Translate(string $Text): string
    {
        return $Text;
    }

    protected function ReadPropertyString(string $Name): string
    {
        return $this->SelectedList;
    }

    protected function RegisterParent(): int
    {
        $this->ParentRegistered = true;
        return 1;
    }

    protected function HasActiveParent(): bool
    {
        return $this->ParentRegistered && $this->Active;
    }

    protected function ReadAttributeString(string $Name): string
    {
        return json_encode($this->Lists);
    }

    protected function WriteAttributeString(string $Name, string $Value): bool
    {
        $this->Lists = json_decode($Value, true, 512, JSON_THROW_ON_ERROR);
        return true;
    }

    protected function SendDataToParent(string $Data): string
    {
        ++$this->Requests;
        if (json_decode($Data, true) !== json_decode(\Bring\Api::GetLists(), true)) {
            throw new RuntimeException('Unexpected gateway request');
        }
        return $this->Response;
    }

    protected function SendDebug(string $Message, mixed $Data, int $Format): bool
    {
        return true;
    }
}
