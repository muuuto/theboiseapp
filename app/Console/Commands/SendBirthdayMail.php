<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use App\Console\Mail\BirthdayReminderMail;
use Illuminate\Support\Facades\Mail;

class SendBirthdayMail extends Command
{
    protected $signature = 'notifications:birthday';
    protected $description = 'Generate birthday notifications for users';

    private $greetings = [
        "italian" => "Tanti auguri di buon compleanno",
        "english" => "Happy birthday",
        "french" => "Joyeux anniversaire",
        "spanish" => "Feliz cumpleaños",
        "german" => "Alles Gute zum Geburtstag",
        "portuguese" => "Feliz aniversário",
        "dutch" => "Fijne verjaardag",
        "swedish" => "Grattis på födelsedagen",
        "norwegian" => "Gratulerer med dagen",
        "danish" => "Tillykke med fødselsdagen",
        "finnish" => "Hyvää syntymäpäivää",
        "polish" => "Wszystkiego najlepszego z okazji urodzin",
        "czech" => "Všechno nejlepší k narozeninám",
        "slovak" => "Všetko najlepšie k narodeninám",
        "hungarian" => "Boldog születésnapot",
        "romanian" => "La mulți ani",
        "bulgarian" => "Честит рожден ден",
        "greek" => "Χρόνια πολλά",
        "serbian" => "Срећан рођендан",
        "croatian" => "Sretan rođendan",
        "slovenian" => "Vse najboljše",
        "bosnian" => "Sretan rođendan",
        "albanian" => "Gëzuar ditëlindjen",
        "turkish" => "Doğum günün kutlu olsun",
        "armenian" => "Ծնունդդ շնորհավոր",
        "georgian" => "გილოცავ დაბადების დღეს",
        "arabic" => "عيد ميلاد سعيد",
        "hebrew" => "יום הולדת שמח",
        "persian" => "تولدت مبارک",
        "urdu" => "سالگرہ مبارک",
        "hindi" => "जन्मदिन मुबारक हो",
        "bengali" => "শুভ জন্মদিন",
        "punjabi" => "ਜਨਮਦਿਨ ਮੁਬਾਰਕ",
        "tamil" => "பிறந்த நாள் வாழ்த்துகள்",
        "telugu" => "పుట్టినరోజు శుభాకాంక్షలు",
        "kannada" => "ಹುಟ್ಟುಹಬ್ಬದ ಶುಭಾಶಯಗಳು",
        "malayalam" => "ജന്മദിനാശംസകൾ",
        "marathi" => "वाढदिवसाच्या शुभेच्छा",
        "gujarati" => "જન્મદિવસની શુભકામનાઓ",
        "sinhalese" => "සුභ උපන් දිනයක්",
        "thai" => "สุขสันต์วันเกิด",
        "vietnamese" => "Chúc mừng sinh nhật",
        "indonesian" => "Selamat ulang tahun",
        "malay" => "Selamat hari jadi",
        "tagalog" => "Maligayang kaarawan",
        "japanese" => "お誕生日おめでとう",
        "korean" => "생일 축하합니다",
        "chinese_simplified" => "生日快乐",
        "chinese_traditional" => "生日快樂",
        "mongolian" => "Төрсөн өдрийн мэнд",
        "kazakh" => "Туған күніңмен",
        "uzbek" => "Tug‘ilgan kuning bilan",
        "azerbaijani" => "Ad günün mübarək",
        "swahili" => "Heri ya siku ya kuzaliwa",
        "zulu" => "Usuku lokuzalwa oluhle",
        "xhosa" => "Ulonwabo lokuzalwa",
        "afrikaans" => "Gelukkige verjaarsdag",
        "amharic" => "መልካም ልደት",
        "somali" => "Dhalasho farxad leh",
        "yoruba" => "O ku ojo ibi",
        "igbo" => "Ezi ncheta ọmụmụ",
        "hausa" => "Barka da ranar haihuwa",
        "maori" => "Rā whānau",
        "icelandic" => "Til hamingju með afmælið",
        "welsh" => "Pen-blwydd hapus",
        "irish" => "Lá breithe sona duit",
        "scottish_gaelic" => "Co-là breith sona dhut",
        "basque" => "Zorionak",
        "catalan" => "Feliç aniversari",
        "galician" => "Feliz aniversario",
        "luxembourgish" => "Alles Guddes fir däi Gebuertsdag",
        "latvian" => "Daudz laimes dzimšanas dienā",
        "lithuanian" => "Su gimtadieniu",
        "estonian" => "Palju õnne sünnipäevaks",
        "maltese" => "Awguri",
        "esperanto" => "Feliĉan naskiĝtagon",
        "samoan" => "Manuia lou aso fanau"
    ];

    public function handle()
    {
        $today = now();

        $users = User::whereMonth('birthday', $today->month)
            ->whereDay('birthday', $today->day)
            ->get();

        foreach ($users as $user) {
            $key = array_rand($this->greetings);
            $greeting = $this->greetings[$key];

            // Carbon 3 returns a signed float: count from the birthday to today and keep whole years
            $age = (int) Carbon::parse($user->birthday)->diffInYears($today);

            Mail::to($user->email)
                ->send(new BirthdayReminderMail(
                    $user,
                    $key,
                    $greeting,
                    $age
                ));
        }
    }
}
