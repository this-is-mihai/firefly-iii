<?php
/**
 * RuleControllerTest.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Carbon\Carbon;
use FireflyIII\Jobs\ExecuteRuleOnExistingTransactions;
use FireflyIII\Jobs\Job;
use FireflyIII\Models\Bill;
use FireflyIII\Models\Rule;
use FireflyIII\Models\RuleGroup;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\Repositories\Rule\RuleRepositoryInterface;
use FireflyIII\Repositories\RuleGroup\RuleGroupRepositoryInterface;
use FireflyIII\TransactionRules\TransactionMatcher;
use Illuminate\Support\Collection;
use Log;
use Queue;
use Tests\TestCase;

/**
 * Class RuleControllerTest
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RuleControllerTest extends TestCase
{
    /**
     *
     */
    public function setUp()
    {
        parent::setUp();
        Log::debug(sprintf('Now in %s.', \get_class($this)));
    }


    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testCreate(): void
    {
        // mock stuff
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $billRepos    = $this->mock(BillRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $this->be($this->user());
        $response = $this->get(route('rules.create', [1]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
        $response->assertViewHas('returnToBill', false);
        $response->assertViewHas('bill', null);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testCreateBill(): void
    {
        // mock stuff
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $billRepos    = $this->mock(BillRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $billRepos->shouldReceive('find')->withArgs([1])->andReturn(Bill::find(1))->once();

        $this->be($this->user());
        $response = $this->get(route('rules.create', [1]) . '?return=true&fromBill=1');
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
        $response->assertViewHas('returnToBill', true);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testCreatePreviousInput(): void
    {
        $old = [
            'rule-trigger'       => ['description_is'],
            'rule-trigger-stop'  => ['1'],
            'rule-trigger-value' => ['X'],
            'rule-action'        => ['set_category'],
            'rule-action-stop'   => ['1'],
            'rule-action-value'  => ['x'],
        ];
        $this->session(['_old_input' => $old]);

        // mock stuff
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $this->be($this->user());
        $response = $this->get(route('rules.create', [1]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testCreateReturn(): void
    {
        // mock stuff
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $billRepos    = $this->mock(BillRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $this->be($this->user());
        $response = $this->get(route('rules.create', [1]) . '?return=true');
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
        $response->assertViewHas('returnToBill', true);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testDelete(): void
    {
        // mock stuff
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $this->be($this->user());
        $response = $this->get(route('rules.delete', [1]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testDestroy(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('destroy');

        $this->session(['rules.delete.uri' => 'http://localhost']);
        $this->be($this->user());
        $response = $this->post(route('rules.destroy', [1]));
        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $response->assertRedirect(route('index'));
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testDown(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('moveDown');

        $this->be($this->user());
        $response = $this->get(route('rules.down', [1]));
        $response->assertStatus(302);
        $response->assertRedirect(route('rules.index'));
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testEdit(): void
    {
        // mock stuff
        $groupRepos   = $this->mock(RuleGroupRepositoryInterface::class);
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('getPrimaryTrigger')->andReturn(new Rule);
        $groupRepos->shouldReceive('get')->andReturn(new Collection);

        $this->be($this->user());
        $response = $this->get(route('rules.edit', [1]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testEditPreviousInput(): void
    {
        $old = [
            'rule-trigger'       => ['description_is'],
            'rule-trigger-stop'  => ['1'],
            'rule-trigger-value' => ['X'],
            'rule-action'        => ['set_category'],
            'rule-action-stop'   => ['1'],
            'rule-action-value'  => ['x'],
        ];
        $this->session(['_old_input' => $old]);

        // mock stuff
        $groupRepos   = $this->mock(RuleGroupRepositoryInterface::class);
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('getPrimaryTrigger')->andReturn(new Rule);
        $groupRepos->shouldReceive('get')->andReturn(new Collection);

        $this->be($this->user());
        $response = $this->get(route('rules.edit', [1]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testExecute(): void
    {
        $account      = $this->user()->accounts()->find(1);
        $accountRepos = $this->mock(AccountRepositoryInterface::class);
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $this->session(['first' => new Carbon('2010-01-01')]);
        $accountRepos->shouldReceive('getAccountsById')->andReturn(new Collection([$account]));
        Queue::fake();

        $data = [
            'accounts'   => [1],
            'start_date' => '2017-01-01',
            'end_date'   => '2017-01-02',
        ];

        $this->be($this->user());
        $response = $this->post(route('rules.execute', [1]), $data);
        $response->assertStatus(302);
        $response->assertSessionHas('success');

        Queue::assertPushed(
            ExecuteRuleOnExistingTransactions::class, function (Job $job) {
            return $job->getRule()->id === 1;
        }
        );
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testIndex(): void
    {
        // mock stuff
        $repository     = $this->mock(RuleRepositoryInterface::class);
        $ruleGroupRepos = $this->mock(RuleGroupRepositoryInterface::class);
        $journalRepos   = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $ruleGroupRepos->shouldReceive('count')->andReturn(0);
        $ruleGroupRepos->shouldReceive('store');
        $repository->shouldReceive('getFirstRuleGroup')->andReturn(new RuleGroup);
        $ruleGroupRepos->shouldReceive('getRuleGroupsWithRules')->andReturn(new Collection);
        $repository->shouldReceive('count')->andReturn(0);
        $repository->shouldReceive('store');

        $this->be($this->user());
        $response = $this->get(route('rules.index'));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testReorderRuleActions(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $data = ['actions' => [1, 2, 3]];
        $repository->shouldReceive('reorderRuleActions')->once();

        $this->be($this->user());
        $response = $this->post(route('rules.reorder-actions', [1]), $data);
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testReorderRuleTriggers(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);

        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $data = ['triggers' => [1, 2, 3]];
        $repository->shouldReceive('reorderRuleTriggers')->once();

        $this->be($this->user());
        $response = $this->post(route('rules.reorder-triggers', [1]), $data);
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testSelectTransactions(): void
    {
        $accountRepos = $this->mock(AccountRepositoryInterface::class);
        $accountRepos->shouldReceive('getAccountsByType')->andReturn(new Collection);

        $this->be($this->user());
        $response = $this->get(route('rules.select-transactions', [1]));
        $response->assertStatus(200);
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers       \FireflyIII\Http\Controllers\RuleController
     * @covers       \FireflyIII\Http\Requests\RuleFormRequest
     */
    public function testStore(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);

        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('store')->andReturn(new Rule);
        $repository->shouldReceive('find')->withArgs([0])->andReturn(new Rule)->once();

        $this->session(['rules.create.uri' => 'http://localhost']);
        $data = [
            'rule_group_id'      => 1,
            'active'             => 1,
            'title'              => 'A',
            'trigger'            => 'store-journal',
            'description'        => 'D',
            'rule-trigger'       => [
                1 => 'from_account_starts',
            ],
            'rule-trigger-value' => [
                1 => 'B',
            ],
            'rule-action'        => [
                1 => 'set_category',
            ],
            'rule-action-value'  => [
                1 => 'C',
            ],
        ];
        $this->be($this->user());
        $response = $this->post(route('rules.store', [1]), $data);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testTestTriggers(): void
    {
        $data = [
            'rule-trigger'       => ['description_is'],
            'rule-trigger-value' => ['Bla bla'],
            'rule-trigger-stop'  => ['1'],
        ];

        // mock stuff
        $matcher      = $this->mock(TransactionMatcher::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $matcher->shouldReceive('setLimit')->withArgs([10])->andReturnSelf()->once();
        $matcher->shouldReceive('setRange')->withArgs([200])->andReturnSelf()->once();
        $matcher->shouldReceive('setTriggers')->andReturnSelf()->once();
        $matcher->shouldReceive('findTransactionsByTriggers')->andReturn(new Collection);

        $this->be($this->user());
        $uri      = route('rules.test-triggers') . '?' . http_build_query($data);
        $response = $this->get($uri);
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testTestTriggersByRule(): void
    {

        $matcher = $this->mock(TransactionMatcher::class);

        $matcher->shouldReceive('setLimit')->withArgs([10])->andReturnSelf()->once();
        $matcher->shouldReceive('setRange')->withArgs([200])->andReturnSelf()->once();
        $matcher->shouldReceive('setRule')->andReturnSelf()->once();
        $matcher->shouldReceive('findTransactionsByRule')->andReturn(new Collection);

        $this->be($this->user());
        $response = $this->get(route('rules.test-triggers-rule', [1]));
        $response->assertStatus(200);

    }

    /**
     * This actually hits an error and not the actually code but OK.
     *
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testTestTriggersError(): void
    {
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $this->be($this->user());
        $uri      = route('rules.test-triggers');
        $response = $this->get($uri);
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testTestTriggersMax(): void
    {
        $data = [
            'rule-trigger'       => ['description_is'],
            'rule-trigger-value' => ['Bla bla'],
            'rule-trigger-stop'  => ['1'],
        ];
        $set  = factory(Transaction::class, 10)->make();

        // mock stuff
        $matcher      = $this->mock(TransactionMatcher::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);

        $matcher->shouldReceive('setLimit')->withArgs([10])->andReturnSelf()->once();
        $matcher->shouldReceive('setRange')->withArgs([200])->andReturnSelf()->once();
        $matcher->shouldReceive('setTriggers')->andReturnSelf()->once();
        $matcher->shouldReceive('findTransactionsByTriggers')->andReturn($set);

        $this->be($this->user());
        $uri      = route('rules.test-triggers') . '?' . http_build_query($data);
        $response = $this->get($uri);
        $response->assertStatus(200);
    }

    /**
     * @covers \FireflyIII\Http\Controllers\RuleController
     */
    public function testUp(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('moveUp');

        $this->be($this->user());
        $response = $this->get(route('rules.up', [1]));
        $response->assertStatus(302);
        $response->assertRedirect(route('rules.index'));
    }

    /**
     * @covers       \FireflyIII\Http\Controllers\RuleController
     * @covers       \FireflyIII\Http\Requests\RuleFormRequest
     */
    public function testUpdate(): void
    {
        // mock stuff
        $repository   = $this->mock(RuleRepositoryInterface::class);
        $journalRepos = $this->mock(JournalRepositoryInterface::class);
        $rule         = Rule::find(1);
        $journalRepos->shouldReceive('firstNull')->once()->andReturn(new TransactionJournal);
        $repository->shouldReceive('find')->withArgs([1])->andReturn($rule)->once();
        $repository->shouldReceive('update');

        $data = [
            'rule_group_id'      => 1,
            'id'                 => 1,
            'title'              => 'Your first default rule',
            'trigger'            => 'store-journal',
            'active'             => 1,
            'description'        => 'This rule is an example. You can safely delete it.',
            'rule-trigger'       => [
                1 => 'description_is',
            ],
            'rule-trigger-value' => [
                1 => 'something',
            ],
            'rule-action'        => [
                1 => 'prepend_description',
            ],
            'rule-action-value'  => [
                1 => 'Bla bla',
            ],
        ];
        $this->session(['rules.edit.uri' => 'http://localhost']);
        $this->be($this->user());
        $response = $this->post(route('rules.update', [1]), $data);
        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }
}
