<?php

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create an admin user
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'role' => 'admin',
            ],
        );

        // Create a test agent user
        $agent = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'agent',
            ],
        );

        // Create additional agents
        User::query()->updateOrCreate(
            ['email' => 'peter@helpdesk.test'],
            [
                'name' => 'Peter',
                'password' => 'password',
                'role' => 'agent',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'patricia@helpdesk.test'],
            [
                'name' => 'Patricia',
                'password' => 'password',
                'role' => 'agent',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'aleksander@helpdesk.test'],
            [
                'name' => 'Aleksander',
                'password' => 'password',
                'role' => 'agent',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'agata@helpdesk.test'],
            [
                'name' => 'Agata',
                'password' => 'password',
                'role' => 'agent',
            ],
        );

        // Get agents for assignment
        $peter = User::firstWhere('email', 'peter@helpdesk.test');
        $patricia = User::firstWhere('email', 'patricia@helpdesk.test');
        $aleksander = User::firstWhere('email', 'aleksander@helpdesk.test');
        $agata = User::firstWhere('email', 'agata@helpdesk.test');

        $tickets = [
            [
                'number' => 'HD-2048',
                'requester_name' => 'Viola Holmes',
                'requester_email' => 'viola@acme.com',
                'subject' => 'Invoice 25032019/B/567 requires correction',
                'assignee' => $peter->id,
                'status' => 'resolved',
                'priority' => 'medium',
                'channel' => 'email',
                'messages' => [
                    ['customer', 'Viola Holmes', 'viola@acme.com', 'Dzień dobry, na fakturze widzę błędny numer NIP. Czy możecie wystawić korektę?'],
                    ['agent', 'Peter', 'peter@helpdesk.test', 'Dziękuję za zgłoszenie. Sprawdziłem dokument i przygotowałem korektę do akceptacji księgowości.'],
                    ['customer', 'Viola Holmes', 'viola@acme.com', 'Super, dziękuję. Po otrzymaniu dokumentu zamknę temat po swojej stronie.'],
                ],
            ],
            [
                'number' => 'HD-2047',
                'requester_name' => 'Earl McDonald',
                'requester_email' => 'earlmc@yahoo.com',
                'subject' => 'Forwarding configuration for sales mailbox',
                'assignee' => $patricia->id,
                'status' => 'open',
                'priority' => 'high',
                'channel' => 'chat',
                'messages' => [
                    ['customer', 'Earl McDonald', 'earlmc@yahoo.com', 'Nie działa przekierowanie ze skrzynki sales@ na mój adres. Wiadomości zostają tylko w głównej skrzynce.'],
                    ['agent', 'Patricia', 'patricia@helpdesk.test', 'Widzę konflikt z regułą archiwizacji. Wyłączę ją testowo i poproszę o potwierdzenie po kolejnym mailu.'],
                ],
            ],
            [
                'number' => 'HD-2046',
                'requester_name' => 'Marian Logan',
                'requester_email' => 'mlogan@logina.biz',
                'subject' => 'LiveChat - pre-chat survey is missing',
                'assignee' => $aleksander->id,
                'status' => 'in_progress',
                'priority' => 'medium',
                'channel' => 'chat',
                'messages' => [
                    ['customer', 'Marian Logan', 'mlogan@logina.biz', 'Formularz przed rozmową zniknął z widgetu po ostatniej zmianie konfiguracji.'],
                    ['agent', 'Aleksander', 'aleksander@helpdesk.test', 'Potrzebuję zrzutu ekranu ustawień widgetu i adresu strony, na której problem występuje.'],
                ],
            ],
            [
                'number' => 'HD-2045',
                'requester_name' => 'Douglas Olson',
                'requester_email' => 'douglas@olson.tv',
                'subject' => 'Automatic responder sends outdated signature',
                'assignee' => null,
                'status' => 'open',
                'priority' => 'low',
                'channel' => 'email',
                'messages' => [
                    ['customer', 'Douglas Olson', 'douglas@olson.tv', 'Autoresponder nadal wysyła starą stopkę z poprzednim numerem telefonu.'],
                ],
            ],
            [
                'number' => 'HD-2044',
                'requester_name' => 'Estelle Nguyen',
                'requester_email' => 'estelle@gmail.com',
                'subject' => 'Case #678234 - missing attachment',
                'assignee' => null,
                'status' => 'closed',
                'priority' => 'medium',
                'channel' => 'email',
                'messages' => [
                    ['customer', 'Estelle Nguyen', 'estelle@gmail.com', 'W zgłoszeniu nie widzę załącznika, który dodawałam w formularzu.'],
                    ['agent', 'Test User', 'test@example.com', 'Załącznik został odzyskany z kolejki plików i dodany do sprawy.'],
                ],
            ],
            [
                'number' => 'HD-2043',
                'requester_name' => 'Bobby Huff',
                'requester_email' => 'bobbyhuff@yahoo.com',
                'subject' => 'Next license renewal and billing details',
                'assignee' => $agata->id,
                'status' => 'in_progress',
                'priority' => 'high',
                'channel' => 'email',
                'messages' => [
                    ['customer', 'Bobby Huff', 'bobbyhuff@yahoo.com', 'Chcemy odnowić licencję, ale potrzebujemy najpierw aktualnej wyceny dla 35 stanowisk.'],
                    ['agent', 'Agata', 'agata@helpdesk.test', 'Przekazałam zapytanie do działu rozliczeń. Wrócę z ofertą, gdy tylko ją dostanę.'],
                ],
            ],
        ];

        foreach ($tickets as $ticketData) {
            $messages = $ticketData['messages'];
            unset($ticketData['messages']);

            $ticket = Ticket::query()->updateOrCreate(
                ['number' => $ticketData['number']],
                $ticketData,
            );

            if ($ticket->messages()->doesntExist()) {
                foreach ($messages as $index => [$type, $name, $email, $body]) {
                    $ticket->messages()->create([
                        'user_id' => $type === 'agent' && $email === $agent->email ? $agent->id : null,
                        'author_name' => $name,
                        'author_email' => $email,
                        'author_type' => $type,
                        'body' => $body,
                        'created_at' => now()->subMinutes((count($messages) - $index) * 7),
                        'updated_at' => now()->subMinutes((count($messages) - $index) * 7),
                    ]);
                }
            }
        }

        $this->call(EnglishTicketDemoSeeder::class);

        $this->seedKnowledgeArticles();
    }

    private function seedKnowledgeArticles(): void
    {
        $articles = [
            [
                'title' => 'Nie działa reset hasła',
                'slug' => 'nie-dziala-reset-hasla',
                'category' => 'Konto i logowanie',
                'problem' => 'Klient nie otrzymuje wiadomości resetu hasła albo link resetujący wygasł.',
                'symptoms' => "Brak maila resetującego\nLink prowadzi do błędu lub jest nieaktywny\nKlient próbuje resetować hasło kilka razy z rzędu",
                'solution' => "1. Sprawdź, czy adres e-mail w zgłoszeniu zgadza się z kontem klienta.\n2. Poproś klienta o sprawdzenie folderu Spam/Oferty.\n3. Wygeneruj nowy link resetu hasła, jeśli poprzedni ma więcej niż 30 minut.\n4. Jeśli wiadomość nie wychodzi, sprawdź kolejkę maili i Mailpit.\n5. Po udanym resecie poproś klienta o ponowne logowanie w trybie incognito.",
                'customer_reply' => "Dzień dobry,\n\nprzygotowałem nowy link do resetu hasła. Proszę skorzystać z najnowszej wiadomości e-mail i zignorować wcześniejsze linki, ponieważ mogły już wygasnąć. Jeśli wiadomość nie pojawi się w skrzynce głównej, proszę sprawdzić folder Spam lub Oferty.\n\nPo zmianie hasła najlepiej zalogować się ponownie w nowej karcie lub w trybie incognito.",
                'tags' => ['hasło', 'reset', 'logowanie', 'email'],
            ],
            [
                'title' => 'Brak załącznika w zgłoszeniu',
                'slug' => 'brak-zalacznika-w-zgloszeniu',
                'category' => 'Załączniki',
                'problem' => 'Klient twierdzi, że dodał plik, ale załącznik nie jest widoczny przy tickecie.',
                'symptoms' => "W rozmowie jest wzmianka o pliku, ale panel plików jest pusty\nPlik ma nietypowy format lub duży rozmiar\nKlient wysłał wiadomość z telefonu",
                'solution' => "1. Sprawdź panel Multimedia i Pliki przy tickecie.\n2. Zweryfikuj, czy wiadomość klienta nie została wysłana bez pliku.\n3. Poproś klienta o ponowne przesłanie pliku w formacie PDF, PNG, JPG lub ZIP.\n4. Jeśli plik jest duży, poproś o kompresję albo podział na części.\n5. Po otrzymaniu pliku potwierdź, że jest widoczny w zgłoszeniu.",
                'customer_reply' => "Dzień dobry,\n\nnie widzę załącznika przy zgłoszeniu. Proszę przesłać plik ponownie, najlepiej w formacie PDF, PNG, JPG albo ZIP. Jeśli plik jest duży, proszę go skompresować lub podzielić na mniejsze części.\n\nGdy tylko plik pojawi się w zgłoszeniu, potwierdzę odbiór i przejdę do dalszej weryfikacji.",
                'tags' => ['załącznik', 'plik', 'upload', 'pdf'],
            ],
            [
                'title' => 'Przekierowanie poczty nie działa',
                'slug' => 'przekierowanie-poczty-nie-dziala',
                'category' => 'Poczta',
                'problem' => 'Wiadomości nie są przekazywane na wskazany adres albo zostają tylko w skrzynce źródłowej.',
                'symptoms' => "Klient nie otrzymuje kopii wiadomości\nReguła przekierowania istnieje, ale nie działa\nWiadomości trafiają do folderu archiwum lub spamu",
                'solution' => "1. Sprawdź adres docelowy i literówki.\n2. Zweryfikuj kolejność reguł pocztowych.\n3. Wyłącz reguły archiwizacji lub filtrowania, które mogą przejmować wiadomości.\n4. Wyślij testową wiadomość i sprawdź log dostarczenia.\n5. Jeśli domena blokuje forwarding, zaproponuj alias lub grupę odbiorców.",
                'customer_reply' => "Dzień dobry,\n\nsprawdziłem konfigurację przekierowania. Najczęstszą przyczyną jest konflikt z inną regułą pocztową albo blokada po stronie domeny. Wykonam test dostarczenia i zweryfikuję kolejność reguł.\n\nPo teście dam znać, czy wystarczy poprawić regułę, czy lepiej zastosować alias lub grupę odbiorców.",
                'tags' => ['poczta', 'forwarding', 'przekierowanie', 'email'],
            ],
            [
                'title' => 'Błędne dane na fakturze',
                'slug' => 'bledne-dane-na-fakturze',
                'category' => 'Rozliczenia',
                'problem' => 'Klient zgłasza nieprawidłowe dane na fakturze, np. NIP, adres, nazwę firmy lub kwotę.',
                'symptoms' => "Klient podaje numer faktury\nWiadomość dotyczy korekty danych firmowych\nPotrzebna jest weryfikacja księgowości",
                'solution' => "1. Poproś o numer faktury i poprawne dane.\n2. Zweryfikuj, czy faktura jest już zaksięgowana.\n3. Przekaż sprawę do rozliczeń lub księgowości.\n4. Jeśli korekta jest możliwa, przygotuj potwierdzenie terminu.\n5. Po wystawieniu korekty dodaj dokument do zgłoszenia.",
                'customer_reply' => "Dzień dobry,\n\nprzyjąłem zgłoszenie korekty danych na fakturze. Proszę o przesłanie numeru faktury oraz poprawnych danych, które mają się znaleźć na dokumencie.\n\nPo weryfikacji przekażę sprawę do rozliczeń i wrócę z informacją o korekcie.",
                'tags' => ['faktura', 'korekta', 'rozliczenia', 'nip'],
            ],
            [
                'title' => 'Widget czatu nie wyświetla formularza przed rozmową',
                'slug' => 'widget-czatu-brak-formularza',
                'category' => 'Czat',
                'problem' => 'Formularz przed rozmową nie pojawia się w widżecie czatu po zmianie konfiguracji.',
                'symptoms' => "Widget uruchamia rozmowę bez pytań wstępnych\nProblem występuje tylko na wybranej stronie\nOstatnio zmieniano konfigurację widżetu",
                'solution' => "1. Poproś klienta o adres strony i zrzut ustawień widżetu.\n2. Sprawdź, czy formularz pre-chat jest aktywny dla danego kanału.\n3. Zweryfikuj, czy reguły widoczności nie omijają formularza.\n4. Wyczyść cache strony lub poproś o test w trybie incognito.\n5. Po zmianie konfiguracji wykonaj testową rozmowę.",
                'customer_reply' => "Dzień dobry,\n\nsprawdzę konfigurację formularza przed rozmową. Proszę przesłać adres strony, na której problem występuje, oraz zrzut ekranu ustawień widżetu.\n\nPo weryfikacji wykonam testową rozmowę i potwierdzę, czy formularz pojawia się poprawnie.",
                'tags' => ['chat', 'widget', 'formularz', 'pre-chat'],
            ],
            [
                'title' => 'Autoresponder wysyła starą stopkę',
                'slug' => 'autoresponder-stara-stopka',
                'category' => 'Poczta',
                'problem' => 'Automatyczna odpowiedź zawiera starą stopkę, numer telefonu lub nieaktualne dane firmy.',
                'symptoms' => "Klient otrzymuje poprawną wiadomość, ale z błędną stopką\nStopka różni się od tej w panelu użytkownika\nProblem dotyczy tylko autorespondera",
                'solution' => "1. Sprawdź szablon autorespondera, nie tylko stopkę użytkownika.\n2. Zweryfikuj, czy aktywny jest właściwy język lub wariant szablonu.\n3. Wyczyść cache konfiguracji, jeśli system go używa.\n4. Wyślij testową wiadomość na skrzynkę klienta.\n5. Potwierdź zmianę po stronie klienta.",
                'customer_reply' => "Dzień dobry,\n\nsprawdzę szablon autorespondera, ponieważ automatyczne odpowiedzi mogą korzystać z osobnej stopki niż zwykłe wiadomości. Po aktualizacji wykonam test i potwierdzę, czy nowa stopka jest już wysyłana poprawnie.",
                'tags' => ['autoresponder', 'stopka', 'email', 'szablon'],
            ],
            [
                'title' => 'Klient nie otrzymuje powiadomień e-mail',
                'slug' => 'klient-nie-otrzymuje-powiadomien-email',
                'category' => 'Powiadomienia',
                'problem' => 'Klient nie dostaje wiadomości o nowych odpowiedziach lub zmianach w zgłoszeniu.',
                'symptoms' => "Brak maili mimo nowych wiadomości w tickecie\nWiadomości są widoczne w systemie\nProblem może dotyczyć jednej domeny",
                'solution' => "1. Sprawdź poprawność adresu e-mail klienta.\n2. Zweryfikuj Mailpit lub log wysyłki.\n3. Poproś klienta o sprawdzenie spamu i filtrów.\n4. Jeśli domena odrzuca wiadomości, sprawdź odpowiedź SMTP.\n5. Wyślij testowe powiadomienie po poprawce.",
                'customer_reply' => "Dzień dobry,\n\nsprawdzę wysyłkę powiadomień na wskazany adres e-mail. Proszę jednocześnie zweryfikować folder Spam oraz reguły pocztowe, które mogły przenieść wiadomości poza skrzynkę główną.\n\nPo stronie systemu sprawdzę log dostarczenia i wrócę z wynikiem testu.",
                'tags' => ['powiadomienia', 'email', 'mailpit', 'smtp'],
            ],
            [
                'title' => 'Zgłoszenie ma nieprawidłowy priorytet',
                'slug' => 'zgloszenie-ma-nieprawidlowy-priorytet',
                'category' => 'Obsługa zgłoszeń',
                'problem' => 'Ticket został utworzony z za wysokim lub za niskim priorytetem.',
                'symptoms' => "Klient prosi o szybszą obsługę\nPriorytet nie zgadza się z typem problemu\nTicket trafił do złej kolejki SLA",
                'solution' => "1. Oceń wpływ problemu na pracę klienta.\n2. Sprawdź, czy problem blokuje cały zespół, jednego użytkownika czy jest kosmetyczny.\n3. Zmień priorytet zgodnie z zasadami SLA.\n4. Dodaj krótką notatkę w odpowiedzi, dlaczego priorytet został zmieniony.\n5. Jeśli problem jest pilny, przypisz go do dostępnego agenta.",
                'customer_reply' => "Dzień dobry,\n\nzweryfikowałem priorytet zgłoszenia i dopasuję go do wpływu problemu na pracę. Jeśli sprawa blokuje pracę większej liczby osób lub uniemożliwia korzystanie z usługi, nadamy jej wyższy priorytet i przyspieszymy obsługę.",
                'tags' => ['priorytet', 'sla', 'kolejka', 'ticket'],
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    ...$article,
                    'is_published' => true,
                ],
            );
        }
    }
}
