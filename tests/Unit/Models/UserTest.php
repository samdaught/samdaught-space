<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\users\User;

final class UserTest extends \Codeception\Test\Unit
{
    public function testFindUserById()
    {

        $user = User::findIdentity(100);

        verify($user)->notEmpty();
        verify($user->username)->equals('admin');
        verify(User::findIdentity(999))->empty();
    }

    public function testFindUserByAccessToken()
    {

        $user = User::findIdentityByAccessToken('100-token');

        verify($user)->notEmpty();
        verify($user->username)->equals('admin');
        verify(User::findIdentityByAccessToken('non-existing'))->empty();
    }

    public function testFindUserByUsername()
    {

        $user = User::findByUsername('admin');

        verify($user)->notEmpty();
        verify(User::findByUsername('not-admin'))->empty();
    }

    public function testValidateUser()
    {

        $user = User::findByUsername('admin');

        verify($user->validateAuthKey('test100key'))->notEmpty();
        verify($user->validateAuthKey('test102key'))->empty();
    }
}
