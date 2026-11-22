<?php

namespace App\Support\Paytr;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

final class PayerContact
{
    public static function fromUser(User $user): array
    {
        return ['payer_name' => $user->name, 'payer_email' => $user->email,
            'payer_phone' => $user->phone, 'payer_address' => $user->address];
    }

    public static function rules(): array
    {
        return ['payer_name' => ['required', 'string', 'max:60'],
            'payer_email' => ['required', 'email', 'max:100', 'regex:/^[\x00-\x7F]+$/'],
            'payer_phone' => ['required', 'string', 'regex:/^[1-9][0-9]{9}$/D'],
            'payer_address' => ['required', 'string', 'max:400']];
    }

    public static function validated(User $user): array
    {
        return Validator::make(self::fromUser($user), self::rules(), [
            'required' => 'Müşteri profilinizdeki :attribute bilgisini Hesabım sayfasından tamamlayın.',
            'payer_phone.regex' => 'Hesabım sayfasındaki telefonu başında 0 olmadan 10 hane olarak düzeltin.',
        ], ['payer_name' => 'ad soyad', 'payer_email' => 'e-posta', 'payer_phone' => 'telefon', 'payer_address' => 'adres'])->validate();
    }

    public static function fieldsToComplete(User $user): array
    {
        $fields = [];
        foreach (['phone', 'address'] as $field) {
            $key = 'payer_'.$field;
            if (Validator::make([$key => $user->{$field}], [$key => self::rules()[$key]])->fails()) $fields[] = $field;
        }

        return $fields;
    }
}
