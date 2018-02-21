<?php

namespace Orchestra\Model\TestCase\Feature\Memory;

use Mockery as m;
use Orchestra\Model\User;
use Orchestra\Model\UserMeta;
use Illuminate\Support\Fluent;
use Illuminate\Container\Container;
use Orchestra\Model\Memory\UserMetaRepository;
use Orchestra\Model\TestCase\Feature\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserMetaRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_be_initiated()
    {
        $stub = new UserMetaRepository('meta', [], $this->app);

        $this->assertEquals([], $stub->initiate());
    }

    /** @test */
    public function it_can_retrieve_data()
    {
        $user = User::faker()->create();

        UserMeta::insert([
            'name' => 'foo', 'user_id' => $user->id, 'value' => 'foobar',
        ]);

        $stub = new UserMetaRepository('meta', [], $this->app);

        $this->assertEquals('foobar', $stub->retrieve('foo/user-1'));
        $this->assertNull($stub->retrieve('foobar/user-1'));
    }

    /** @test */
    public function it_can_update_and_remove_items_on_close()
    {
        $users = User::faker()->times(2)->create();

        UserMeta::insert([
            ['name' => 'foo', 'user_id' => $users[0]->id, 'value' => 'foobar'],
            ['name' => 'foo', 'user_id' => $users[1]->id, 'value' => 'foobar'],
        ]);

        $items = [
            'foo/user-'.$users[0]->id => 'foobar',
            'foobar/user-'.$users[0]->id => 'foo',
            'foo/user-'.$users[1]->id => ':to-be-deleted:',
        ];

        $stub = new UserMetaRepository('meta', [], $this->app);

        $this->assertTrue($stub->finish($items));

        $this->assertDatabaseHas('user_meta', ['name' => 'foobar', 'user_id' => $users[0]->id]);
        $this->assertDatabaseMissing('user_meta', ['name' => 'foo', 'user_id' => $users[1]->id]);
        $this->assertDatabaseMissing('user_meta', ['name' => 'foobar', 'user_id' => $users[1]->id]);
    }
}