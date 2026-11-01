<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class BulkContactSeeder extends Seeder
{
    private const int FAKER_SEED = 20261101;

    private const int CHUNK_SIZE = 1000;

    /**
     * Business domains first, then free-mail domains, so a domain search returns useful groups.
     *
     * @var list<string>
     */
    private const array DOMAINS = [
        'rex.com.au',
        'acmeplumbing.com.au',
        'harbourlegal.com.au',
        'southerncrossrealty.com.au',
        'bluegum.com.au',
        'wattleandco.com.au',
        'outbackfreight.com.au',
        'coastalbuilders.com.au',
        'eucalyptdesign.com.au',
        'sydneyharbourcafe.com.au',
        'melbournetrams.com.au',
        'brisbaneconsulting.com.au',
        'perthmining.com.au',
        'adelaidewines.com.au',
        'tasmaniantimber.com.au',
        'canberrapolicy.com.au',
        'kiwifruit.co.nz',
        'aucklandmarine.co.nz',
        'wellingtonlaw.co.nz',
        'cantaburyfarms.co.nz',
        'otagoexplorer.co.nz',
        'rotoruatours.co.nz',
        'fernleaf.co.nz',
        'pohutukawa.co.nz',
        'kauricapital.co.nz',
        'example.co.nz',
        'example.com',
        'gmail.com',
        'outlook.com',
        'yahoo.com.au',
        'bigpond.com',
        'icloud.com',
        'proton.me',
        'xtra.co.nz',
    ];

    /**
     * Inserts the contacts in chunks of plain arrays, one batched insert per table per chunk.
     */
    public function run(?int $count = null): void
    {
        $count ??= (int) config('contacts.seed.bulk_contacts');

        $faker = fake('en_AU');
        $faker->seed(self::FAKER_SEED);

        for ($seeded = 0; $seeded < $count; $seeded += self::CHUNK_SIZE) {
            $this->insertChunk($faker, min(self::CHUNK_SIZE, $count - $seeded));
        }
    }

    private function insertChunk(
        Generator $faker,
        int $size,
    ): void {
        $now = now()->toDateTimeString();
        $contacts = [];
        $emails = [];
        $phones = [];

        for ($i = 0; $i < $size; $i++) {
            $id = strtolower($faker->uuid());
            $name = $faker->firstName().' '.$faker->lastName();

            $contacts[] = [
                'id' => $id,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($this->emailsFor($faker, $name) as $email) {
                $emails[] = [
                    'contact_id' => $id,
                    'email' => $email->value,
                    'domain' => $email->domain(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($this->phonesFor($faker) as $phone) {
                $phones[] = [
                    'contact_id' => $id,
                    'phone' => $phone->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($contacts, $emails, $phones): void {
            DB::table('contacts')->insertOrIgnore($contacts);
            DB::table('contact_emails')->insertOrIgnore($emails);
            DB::table('contact_phones')->insertOrIgnore($phones);
        });
    }

    /**
     * One to three distinct valid addresses.
     *
     * @return list<EmailAddress>
     */
    private function emailsFor(
        Generator $faker,
        string $name,
    ): array {
        $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
        $local = implode('.', $words === false || $words === [] ? ['contact'] : $words);
        $addresses = [];

        for ($wanted = $faker->numberBetween(1, 3); count($addresses) < $wanted;) {
            $suffix = $faker->boolean(40) ? (string) $faker->numberBetween(1, 99) : '';
            $address = EmailAddress::fromString($local.$suffix.'@'.$faker->randomElement(self::DOMAINS));
            $addresses[$address->value] = $address;
        }

        return array_values($addresses);
    }

    /**
     * One to two distinct valid numbers, about three in four Australian.
     *
     * @return list<PhoneNumber>
     */
    private function phonesFor(Generator $faker): array
    {
        $numbers = [];

        for ($wanted = $faker->numberBetween(1, 2); count($numbers) < $wanted;) {
            $phone = PhoneNumber::fromString($faker->boolean(75) ? $this->australianNumber($faker) : $this->newZealandNumber($faker));
            $numbers[$phone->value] = $phone;
        }

        return array_values($numbers);
    }

    private function australianNumber(Generator $faker): string
    {
        $prefix = $faker->boolean(60) ? '4' : (string) $faker->randomElement([2, 3, 7, 8]);

        return '+61'.$prefix.$faker->numerify('########');
    }

    private function newZealandNumber(Generator $faker): string
    {
        if ($faker->boolean(60)) {
            return '+642'.$faker->numerify($faker->boolean() ? '#######' : '########');
        }

        return '+64'.$faker->randomElement([3, 4, 6, 7, 9]).$faker->numerify('#######');
    }
}
