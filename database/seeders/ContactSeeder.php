<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Contacts\Application\Actions\UpsertContact;
use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Fixed demo contacts: 30 in Australia then 10 in New Zealand. Each row is name, emails, phones.
     *
     * @var list<array{string, list<string>, list<string>}>
     */
    private const array CONTACTS = [
        ['Chloe Smith', ['chloe.smith@rex.com.au'], ['+61412345678', '+61298765432']],
        ['Liam Nguyen', ['liam.nguyen@rex.com.au', 'liam@gmail.com'], ['+61423456789']],
        ['Olivia Brown', ['olivia.brown@rex.com.au'], ['+61387654321']],
        ['Noah Wilson', ['noah.wilson@outlook.com'], ['+61434567890']],
        ['Amelia Taylor', ['amelia.taylor@rex.com.au'], ['+61245678901', '+61445678901']],
        ['Jack Martin', ['jack.martin@bigpond.com'], ['+61456789012']],
        ['Isla Anderson', ['isla.anderson@rex.com.au', 'isla@icloud.com', 'isla.a@example.com'], ['+61367890123']],
        ['Oliver Thompson', ['oliver.thompson@gmail.com'], ['+61467890123']],
        ['Mia White', ['mia.white@rex.com.au'], ['+61278901234']],
        ['William Harris', ['will.harris@yahoo.com.au'], ['+61478901234', '+61398765432']],
        ['Charlotte Clark', ['charlotte.clark@rex.com.au'], ['+61489012345']],
        ['Henry Lewis', ['henry.lewis@outlook.com'], ['+61289012345']],
        ['Grace Walker', ['grace.walker@example.com'], ['+61490123456']],
        ['Lucas Hall', ['lucas.hall@rex.com.au', 'lucas@proton.me'], ['+61390123456']],
        ['Ruby Young', ['ruby.young@gmail.com'], ['+61401234567']],
        ['Ethan King', ['ethan.king@bigpond.com'], ['+61201234567', '+61411234567']],
        ['Sophie Wright', ['sophie.wright@rex.com.au'], ['+61412300001']],
        ['Mason Scott', ['mason.scott@icloud.com'], ['+61312300002']],
        ['Ava Green', ['ava.green@example.com', 'ava@gmail.com'], ['+61412300003']],
        ['Leo Baker', ['leo.baker@rex.com.au'], ['+61212300004']],
        ['Ella Adams', ['ella.adams@yahoo.com.au'], ['+61412300005']],
        ['James Nelson', ['james.nelson@outlook.com'], ['+61312300006', '+61412300006']],
        ['Zoe Hill', ['zoe.hill@example.com'], ['+61412300007']],
        ['Archie Campbell', ['archie.campbell@gmail.com'], ['+61212300008']],
        ['Lily Mitchell', ['lily.mitchell@bigpond.com'], ['+61412300009']],
        ['Max Roberts', ['max.roberts@proton.me'], ['+61312300010']],
        ['Hannah Carter', ['hannah.carter@example.com'], ['+61412300011']],
        ['Jacob Phillips', ['jacob.phillips@icloud.com'], ['+61212300012']],
        ['Evie Evans', ['evie.evans@gmail.com'], ['+61412300013']],
        ['Harry Turner', ['harry.turner@outlook.com'], ['+61312300014']],
        ['Aroha Ngata', ['aroha.ngata@example.co.nz'], ['+6421234567']],
        ['Tane Williams', ['tane.williams@example.co.nz', 'tane@xtra.co.nz'], ['+6422345678', '+6492345678']],
        ['Mere Parata', ['mere.parata@example.co.nz'], ['+6423456789']],
        ['Hemi Walker', ['hemi.walker@gmail.com'], ['+6424567890']],
        ['Kiri Te Rangi', ['kiri.terangi@example.co.nz'], ['+6425678901']],
        ['Ben Fraser', ['ben.fraser@xtra.co.nz'], ['+6426789012']],
        ['Anika Patel', ['anika.patel@example.co.nz', 'anika@outlook.com'], ['+6427890123']],
        ['Rawiri Cook', ['rawiri.cook@gmail.com'], ['+6428901234']],
        ['Tui Henare', ['tui.henare@xtra.co.nz'], ['+6429012345']],
        ['Sam Duncan', ['sam.duncan@example.com'], ['+6421122334']],
    ];

    /**
     * Seeds the demo contacts through UpsertContact under fixed ids, so seeding again changes nothing.
     */
    public function run(): void
    {
        $upsert = app(UpsertContact::class);

        foreach (self::CONTACTS as $index => [$name, $emails, $phones]) {
            $upsert->handle(UpsertContactData::fromValidated([
                'id' => sprintf('c0ffee00-0000-4000-8000-%012d', $index + 1),
                'name' => $name,
                'emails' => $emails,
                'phones' => $phones,
            ]));
        }
    }
}
