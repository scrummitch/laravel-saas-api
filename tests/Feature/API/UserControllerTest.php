<?php

namespace Tests\Feature\API;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_update_user_profile()
    {
        $user = $this->createUser();
        $newUserProfileAttributes = [
            'first_name' => 'albert 12333',
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->email,
            'timezone' => $this->faker->timezone,
        ];

        $this->actingAs($user);
        $pageRes = $this->putJson('/v1/users', $newUserProfileAttributes);
        $pageRes->assertStatus(Response::HTTP_OK);

        $this->assertSame($user->first_name, $newUserProfileAttributes['first_name']);
        $this->assertSame($user->last_name, $newUserProfileAttributes['last_name']);
        $this->assertSame($user->email, $newUserProfileAttributes['email']);
        $this->assertSame($user->timezone, $newUserProfileAttributes['timezone']);
    }

    /**
     * @dataProvider changePasswordDataProvider
     */
    public function test_change_password(
        string $userCurrentPassword,
        array $requestBody,
        int $expectedResponseStatus,
        array $expectedFieldsWithErrors,
        string $expectedNewUserPassword
    ) {
        $user = $this->createUser(['password' => bcrypt($userCurrentPassword)]);
        $this->actingAs($user);

        $pageRes = $this->postJson('/v1/users/change-password', $requestBody);
        $pageRes->assertStatus($expectedResponseStatus);
        $validationErrors = data_get($pageRes, 'errors', []);

        if (empty($expectedFieldsWithErrors)) {
            $this->assertTrue(empty($validationErrors));
        } else {
            foreach ($expectedFieldsWithErrors as $field) {
                $this->assertTrue(isset($validationErrors[$field]));
            }

            $this->assertSame(count($expectedFieldsWithErrors), count($validationErrors));
        }

        $this->assertTrue(Hash::check($expectedNewUserPassword, $user->password));
    }

    public static function changePasswordDataProvider()
    {
        $userCurrentPassword = 'current-password';
        $newUserPassword = 'new-password';

        return [
            //successfull
            [
                $userCurrentPassword,
                [
                    'current_password' => $userCurrentPassword,
                    'password' => $newUserPassword,
                    'password_confirmation' => $newUserPassword,
                ],
                Response::HTTP_OK,
                [],
                $newUserPassword,
            ],

            //no request body
            [
                $userCurrentPassword,
                [
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'current_password',
                    'password',
                    'password_confirmation',
                ],
                $userCurrentPassword,
            ],

            //passwords don't match
            [
                $userCurrentPassword,
                [
                    'current_password' => $userCurrentPassword,
                    'password' => $newUserPassword,
                    'password_confirmation' => 'incorrect-password-confirmation',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'password',
                ],
                $userCurrentPassword,
            ],
            //incorrect current password
            [
                $userCurrentPassword,
                [
                    'current_password' => 'incorrect-password',
                    'password' => $newUserPassword,
                    'password_confirmation' => $newUserPassword,
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [
                    'current_password',
                ],
                $userCurrentPassword,
            ],

        ];
    }
}
