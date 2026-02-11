<?php

namespace Database\Seeders;

use App\Models\Hub;
use App\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ZoneStateSeeder extends Seeder
{
    const STATION_ID = 3;

    public function run(): void
    {
        $this->ensureZoneGeometryColumn();
        $this->ensureStateGeometryColumn();


        $station = Station::find(self::STATION_ID)
            ?? Station::where('name', 'like', '%Sohar%')->first();

        if (!$station) {
            $this->command->error('Station not found. Adjust STATION_ID or provide a valid Station.');
            return;
        }

        $this->command->info("Using Station #{$station->id} ({$station->name})");

        $zones = [
            'Zone01' => [
                'type' => 'MultiPolygon',
                'coordinates' => [
                    [
                        [
                            [56.5013, 24.679],
                            [56.208, 24.5324],
                            [56.0793, 24.6846],
                            [56.078, 24.748],
                            [56.0811, 24.7433],
                            [56.1127, 24.7382],
                            [56.2012, 24.7844],
                            [56.2059, 24.8503],
                            [56.2591, 24.8599],
                            [56.2802, 24.8747],
                            [56.2806, 24.8844],
                            [56.3054, 24.8842],
                            [56.3261, 24.8982],
                            [56.3389, 24.9144],
                            [56.3501, 24.9339],
                            [56.3412, 24.9421],
                            [56.3236, 24.9724],
                            [56.3434, 24.9719],
                            [56.3538, 24.9759],
                            [56.3749, 24.9779],
                            [56.3918, 24.9235],
                            [56.3963, 24.916],
                            [56.4001, 24.9021],
                            [56.4038, 24.8999],
                            [56.4054, 24.8882],
                            [56.4068, 24.8851],
                            [56.4101, 24.8829],
                            [56.4104, 24.8771],
                            [56.4165, 24.8685],
                            [56.4299, 24.8363],
                            [56.441, 24.8246],
                            [56.4476, 24.8085],
                            [56.4546, 24.8001],
                            [56.4632, 24.7729],
                            [56.469, 24.7607],
                            [56.4729, 24.7351],
                            [56.4751, 24.7313],
                            [56.4762, 24.7318],
                            [56.4804, 24.721],
                            [56.4796, 24.7196],
                            [56.4824, 24.7171],
                            [56.4826, 24.7129],
                            [56.4904, 24.699],
                            [56.4943, 24.6954],
                            [56.4935, 24.694],
                            [56.4954, 24.6932],
                            [56.5013, 24.679]
                        ]
                    ]
                ],
            ],

            'Zone02' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.61478781738283, 24.49774375305016],
                        [56.30717062988283, 24.22877970430175],
                        [56.20417380371096, 24.535228083807233],
                        [56.50217795410158, 24.682557756264405],
                        [56.61478781738283, 24.49774375305016],
                    ]
                ],
            ],
            'Zone03' => null,
            'Zone04' => null,
            'Zone05' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.620988823763696, 24.38811572790598],
                        [56.642961480013696, 24.396245350059324],
                        [56.67283055960354, 24.39218060436535],
                        [56.69171331106838, 24.3740439921725],
                        [56.682443596712915, 24.353089839859255],
                        [56.63163182913479, 24.344019056225708],
                        [56.620988823763696, 24.38811572790598],
                    ]
                ],
            ],
            'Zone06' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.3062, 24.2271],
                        [56.6173, 24.499],
                        [56.6237, 24.489],
                        [56.626, 24.4885],
                        [56.6263, 24.4857],
                        [56.6365, 24.4765],
                        [56.6382, 24.4707],
                        [56.6446, 24.4682],
                        [56.6465, 24.4649],
                        [56.651, 24.4626],
                        [56.6901, 24.4268],
                        [56.7057, 24.416],
                        [56.7074, 24.4126],
                        [56.714, 24.4115],
                        [56.7196, 24.4079],
                        [56.7354, 24.3826],
                        [56.7609, 24.349],
                        [56.4682, 24.0772],
                        [56.3126, 24.1404],
                        [56.3062, 24.2271],
                    ]
                ],
            ],
            'Zone07' => null,
            'Zone08' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.79512350868061, 24.27673140999232],
                        [56.80765478919819, 24.28259932758432],
                        [56.81615202735737, 24.26765516363112],
                        [56.806538990247994, 24.260769302924963],
                        [56.79512350868061, 24.27673140999232],
                    ]
                ],
            ],
            'Zone09' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.9846, 24.0768],
                        [56.7647, 23.8365],
                        [56.4682, 24.0772],
                        [56.761, 24.349],
                        [56.8079, 24.2849],
                        [56.8224, 24.2618],
                        [56.8276, 24.2568],
                        [56.8371, 24.2432],
                        [56.8415, 24.2399],
                        [56.8535, 24.2224],
                        [56.8582, 24.2182],
                        [56.8585, 24.2157],
                        [56.8737, 24.2021],
                        [56.886, 24.1774],
                        [56.8904, 24.1751],
                        [56.8937, 24.1682],
                        [56.9068, 24.1504],
                        [56.9493, 24.1054],
                        [56.9846, 24.0768],
                    ]
                ],
            ],
            'Zone10' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [57.1637, 23.504],
                        [56.8747, 23.4908],
                        [56.8571, 23.5119],
                        [56.7647, 23.8365],
                        [56.9846, 24.0768],
                        [57.0196, 24.0504],
                        [57.0257, 24.0468],
                        [57.0304, 24.0468],
                        [57.0635, 24.0185],
                        [57.0726, 24.0126],
                        [57.0782, 24.011],
                        [57.0946, 23.9926],
                        [57.1107, 23.9788],
                        [57.1318, 23.9624],
                        [57.151, 23.9505],
                        [57.1801, 23.9357],
                        [57.2324, 23.9137],
                        [57.3029, 23.8896],
                        [57.3332, 23.8832],
                        [57.3665, 23.8707],
                        [57.3829, 23.8662],
                        [57.383, 23.8645],
                        [57.387, 23.8635],
                        [57.2989, 23.6319],
                        [57.1637, 23.504],
                    ]
                ],
            ],
            'Zone11' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [57.2989, 23.6319],
                        [57.387, 23.8635],
                        [57.383, 23.8645],
                        [57.3834, 23.866],
                        [57.4099, 23.8604],
                        [57.4571, 23.8463],
                        [57.4704, 23.844],
                        [57.5174, 23.8257],
                        [57.5235, 23.8249],
                        [57.526, 23.8265],
                        [57.5276, 23.8254],
                        [57.5268, 23.8229],
                        [57.5329, 23.8196],
                        [57.5876, 23.7971],
                        [57.6544, 23.779],
                        [57.6148, 23.6252],
                        [57.2989, 23.6319],
                    ]
                ],
            ],
            'Zone12' => [
                'type' => 'MultiPolygon',
                'coordinates' => [
                    [
                        [
                            [56.5435577918844, 23.27322449249295],
                            [56.52398839491175, 23.276851393253796],
                            [56.522271781142216, 23.26313172722848],
                            [56.54012456434534, 23.25193417142782],
                            [56.5435577918844, 23.27322449249295],
                        ]
                    ],
                    [
                        [
                            [56.537034659560184, 23.30305675935865],
                            [56.5351463844137, 23.283821075013137],
                            [56.56244054334925, 23.283821075013137],
                            [56.562955527480106, 23.30526395511456],
                            [56.537034659560184, 23.30305675935865],
                        ]
                    ],
                    [
                        [
                            [56.71968236463831, 23.291389544867094],
                            [56.692044882948856, 23.271363698307297],
                            [56.67350545423792, 23.28933979343814],
                            [56.699597983534794, 23.30684050108704],
                            [56.71968236463831, 23.291389544867094],
                        ]
                    ],
                ],
            ],
            'Zone13' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.37963140317895, 24.87626548923045],
                        [56.378944757671135, 24.896820713254986],
                        [56.39714086362817, 24.899934842669133],
                        [56.41911351987817, 24.84698396368169],
                        [56.40057409116723, 24.840129839537592],
                        [56.38512456724145, 24.855083802730608],
                        [56.37963140317895, 24.87626548923045],
                    ]
                ],
            ],
            'Zone14' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [55.840363712793554, 24.33538982603983],
                        [55.969453068262304, 24.223977671442277],
                        [55.83212396669981, 24.20018040626287],
                        [55.7771923260748, 24.230239370804625],
                        [55.75109979677793, 24.23399624260194],
                        [55.7552196698248, 24.26029123979924],
                        [55.840363712793554, 24.33538982603983],
                    ]
                ],
            ],
            'Zone15' => null,
            'Zone16' => null,

            'Zone17' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [56.5237, 22.2538],
                        [56.3257, 22.0153],
                        [55.6242, 21.8701],
                        [55.6667, 22],
                        [55.5667, 22.1553],
                        [55.5273, 22.2131],
                        [55.4816, 22.2872],
                        [55.2135, 22.7022],
                        [55.2207, 22.7649],
                        [55.2281, 22.7915],
                        [55.2243, 22.8075],
                        [55.2237, 22.8173],
                        [55.2266, 22.8251],
                        [55.2219, 22.8327],
                        [55.2182, 22.851],
                        [55.2233, 22.8593],
                        [55.2256, 22.8849],
                        [55.2233, 22.8929],
                        [55.219, 22.8985],
                        [55.2186, 22.9032],
                        [56.3802, 23.4904],
                        [56.84474038085939, 23.505603466492065],
                        [56.863713671874976, 23.48072378880762],
                        [56.6828, 22.6975],
                        [56.5237, 22.2538],
                    ]
                ],
            ],
        ];

        $zoneStateLabels = [
            'Zone01' => ['Shinas', 'شناص'],
            'Zone02' => ['Luwa', 'لوى', 'لوا', 'Luwa'],
            'Zone05' => ['Sohar', 'صحار'],
            'Zone06' => ['Sohar', 'صحار'],
            'Zone08' => ['Saham', 'صحم'],
            'Zone09' => ['Saham', 'صحم'],
            'Zone10' => ['Al-Khaburah', 'الخابورة'],
            'Zone11' => ['As-Suwaiq', 'السويق'],
            'Zone12' => ['Ibri', 'عبري', 'Al Musannah', 'المصنعة'],
            'Zone13' => ['Shinas', 'شناص'],
            'Zone14' => ['Al-Buraimi', 'البريمي'],
            'Zone17' => ['Ibri', 'عبري'],
        ];

        $zoneGovernorateLabels = [
            'Zone05' => ['North Al Batinah', 'شمال الباطنة'],
            'Zone06' => ['North Al Batinah', 'شمال الباطنة'],
            'Zone08' => ['North Al Batinah', 'شمال الباطنة'],
            'Zone09' => ['North Al Batinah', 'شمال الباطنة'],
            'Zone10' => ['North Al Batinah', 'شمال الباطنة'],
            'Zone11' => ['North Al Batinah', 'شمال الباطنة'],
            'Zone14' => ['Al Buraimi', 'البريمي'],
            'Zone17' => ['Ad Dhahirah', 'الظاهرة'],
        ];

        $zonePlaceMap = [
            'Zone05' => [1114, 'Sohar Industrial City'],
            'Zone08' => [1106, "Al-Uwaynat"],
            'Zone12' => [1682, 'Al-Wahrah'],
            'Zone13' => [1064, 'Abu Baqrah'],
            'Zone17' => [1765, "Tanam"],
        ];

        $soharConfig = [
            'zones' => $zones,                 // المصفوفة الكبيرة اللي عندك
            'zoneStateLabels' => $zoneStateLabels,
            'zoneGovernorateLabels' => $zoneGovernorateLabels,
            'zonePlaceMap' => $zonePlaceMap,
        ];

        $soharStation = $station;

        $this->seedForStation($soharStation, $soharConfig);
        // === Muscat config ===

        $muscatHub = Hub::find(1) ?? Hub::where('name', 'like', '%Muscat%')->first();

        if ($muscatHub) {
            // 1) Polygon واحد مشترك بدلاً من تكراره لكل Zone
            $commonPolygon = [
                [
                    [58.335, 23.4887],
                    [58.1183, 23.4622],
                    [58.0929, 23.5609],
                    [58.1248, 23.7034],
                    [58.1229, 23.7039],
                    [58.1231, 23.7062],
                    [58.1435, 23.7004],
                    [58.1715, 23.6896],
                    [58.1776, 23.6890],
                    [58.1896, 23.6818],
                    [58.1943, 23.6810],
                    [58.1943, 23.6790],
                    [58.1987, 23.6787],
                    [58.2085, 23.6718],
                    [58.2163, 23.6637],
                    [58.2163, 23.6612],
                    [58.2212, 23.6599],
                    [58.2318, 23.6504],
                    [58.2537, 23.6374],
                    [58.2612, 23.6313],
                    [58.2851, 23.6196],
                    [58.3160, 23.6099],
                    [58.3418, 23.6046],
                    [58.3701, 23.6054],
                    [58.3910, 23.6035],
                    [58.3841, 23.6033],
                    [58.3350, 23.4887],
                ]
            ];

            // 2) توليد 47 زون بنفس البوليغون المشترك
            $zonesMuscat = [];
            for ($i = 1; $i <= 47; $i++) {
                $zoneKey = "Zone" . str_pad($i, 2, "0", STR_PAD_LEFT);
                $zonesMuscat[$zoneKey] = [
                    'type' => 'Polygon',
                    'coordinates' => $commonPolygon,
                ];
            }

            // 3) Labels — الموجودة من مثالِك لمسقط/السيب (1..13). الباقي سايبه فاضي عمداً.
            $stateLabels = [
                'Zone01' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone02' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone03' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone04' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone05' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone06' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone07' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone08' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone09' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone10' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone11' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone12' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone13' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone14' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone15' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone16' => ['As Muttrah', 'Muttrah', 'مطرح'],
                'Zone17' => ['As Al Amarat', 'Al Amarat', 'العامرات'],
                'Zone18' => ['As Muscat', 'Muscat', 'مسقط'],
                'Zone19' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone20' => ['As Muttrah', 'Muttrah', 'مطرح'],
                'Zone21' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone22' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone23' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone24' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone25' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone26' => ['As Bawshar', 'Bawshar', 'بوشر'],
                'Zone27' => ['As Muttrah', 'Muttrah', 'مطرح'],
                'Zone28' => ['As Muscat', 'Muscat', 'مسقط'],
                'Zone29' => ['As Manah', 'Manah', 'منح'],
                'Zone30' => ['As Bahla', 'Bahla', 'بهلاء'],
                'Zone31' => ['As Bahla', 'Bahla', 'بهلاء'],
                'Zone32' => ['As Bahla', 'Bahla', 'بهلاء'],
                'Zone33' => ['As Al Musannah', 'Al Musannah', 'المصنعة'],
                'Zone34' => ['As Barka', 'Barka', 'بركاء'],
                // 'Zone35' => ['As Seeb', 'Seeb', 'السيب'],
                // 'Zone36' => ['As Seeb', 'Seeb', 'السيب'],
                // 'Zone37' => ['As Seeb', 'Seeb', 'السيب'],
                'Zone38' => ['As Samail', 'Samail', 'سمائل'],
                'Zone39' => ['As Al-Kamil and Al-Wafi', 'Al-Kamil and Al-Wafi', 'الكـامل والـوافـي'],
                'Zone40' => ['As Sur', 'Sur', 'صور'],
                'Zone41' => ['As Muscat', 'Muscat', 'مسقط'],
                'Zone42' => ['As Al Rustaq', 'Al Rustaq', 'الرستاق'],
                'Zone43' => ['As Nakhl', 'Nakhl', 'نخل'],
                'Zone44' => ['As Ibri', 'Ibri', 'عبري'],
                'Zone45' => ['Yanqul', 'Yanqul', 'ينقل'],
                'Zone46' => ['Ibri', 'Ibri', 'عبري'],
                'Zone47' => ['Dhank', 'Dhank', 'ضنك'],

            ];

            $govLabels = [];
            for ($i = 1; $i <= 47; $i++) {
                $zoneKey = "Zone" . str_pad($i, 2, "0", STR_PAD_LEFT);
                $govLabels[$zoneKey] = ['Muscat', 'مسقط'];
            }

            // 4) placeMap — متعبّي IDs المؤكَّدة من places.sql + أسماء من الشيت
            //    (لو مش لاقي ID أكيد، بسيب المصفوفة فاضية [] زي ما كنت عامل، وبتقدر تعبيها لاحقاً)
            $placeMap = [
                // —— Muscat / Seeb (IDs مؤكدة) ——
                'Zone01' => [],
                'Zone02' => [108, 'Al Ghashiba'],
                'Zone03' => [1546, 109, 'Halban', 'Al Maabela'],
                'Zone04' => [2204, 2205, 'Al khoud east', 'Al khoud west'],
                'Zone05' => [103, 'Al Khoudh'],
                'Zone06' => [106, 'Seeb'], // As-Sib
                'Zone07' => [105, 120, 'Al-Rusayl', 'Al Rusayl Industrial City'],
                'Zone08' => [102, 'Al Jafnayn'],
                'Zone09' => [118, 'Hail Al Awamir'],
                'Zone10' => [],
                'Zone11' => [114, 'Al Mawaleh North'],
                'Zone12' => [125, 'Muscat International Airport'],
                'Zone13' => [114, 'Al Mawaleh North'],

                'Zone14' => [91, 'Al Khuwair'],
                'Zone15' => [],
                'Zone16' => [23, 'Hamriyah'],
                'Zone17' => [1652, 48, 'Al-Hajar', 'Al Amarat'], // ["Al-Amarat"]
                'Zone18' => [1687, 27, 'Ar-Rawdah', 'Riyam'], // ['Yiti','Qantab','Bandar Jissah','Riyam','Al-Bustan']
                'Zone19' => [21, 'Wutayyah'], // ['Al Wutayyah / Wattaya']
                'Zone20' => [26, 33, 'Ruwi', 'Wadi Kabir'], // ['Ruwi','Muttrah','Darseit','Al Wadi Al Kabir','Jibroo']
                'Zone21' => [], // ['Falaj Al Sham','Al Ansab']
                'Zone22' => [97, 'Bawshar'], // ['Bawshar','Al Muna']
                'Zone23' => [99, 'Ghala'],
                'Zone24' => [], // ['Al Ghubrah South']
                'Zone25' => [94, 'Al Ghubrah'],
                'Zone26' => [100, 121, 'Madinat Al Sultan Qaboos', 'Madinat Al Irfan'], // ['Madinat Al Sultan Qaboos','Madinat Al Ilam']
                'Zone27' => [95, 32, 'Qurum', 'Mina Al Fahal'], // ['Qurum','Mina Al Fahal']
                'Zone28' => [93, 'Al Azaiba'],
                'Zone29' => [2087, 'Manah'],
                'Zone30' => [1979, 1886, 1956, 1962, 'Bahla', 'Al-Hamra', 'Al-Ghafat', 'Al-Hayshah'], // ['Nizwa','Manah']
                'Zone31' => [1857, 1863, 1856, 1862, 'Izki', 'Qarut ash-Shamaliyah', 'Imti', 'Qarut al-Janubiyah'],
                'Zone32' => [1935, 1934, 'Fanja', 'Bidbid'],
                'Zone33' => [1459, 'Al-Musanaah'], // ['Al Musanaah','Muladdah', "Al 'Uwayd", ...]
                'Zone34' => [1518, 'Barka'], // الشيت فيه Barka/Ar Rumays؛ الموجود عندنا 'Al-Rumais' (Seeb)
                'Zone35' => [],
                'Zone36' => [],
                'Zone37' => [], // ['Ad-Dariz (Ibri)']
                'Zone38' => [2065, 'Samail'], // ["Samail","Lizgh"]
                'Zone39' => [1224, 1196, 1162, 'Jaalan Bani Bu Ali', 'Jaalan Bani Bu Hasan', 'Al-Kamil wa al-Wafi'], // ['Jaalan Bani Bu Ali','Jaalan Bani Bu Hassan','Al Kamil Wal Wafi']
                'Zone40' => [1282, 'Sur'], // ['Sur']
                'Zone41' => [], // ['Muscat']
                'Zone42' => [1354, 1424, 'Ar-Rustaq', 'Al-Awabi'], // ['Al Rustaq','Al Awabi']
                'Zone43' => [1548, 'Nakhal'], // ['Nakhal','Wadi Al Maawil']
                'Zone44' => [1682, 'Al-Wahrah'], // ['Al Wahrah','Al Aynayn','Al Araqi']
                'Zone45' => null,
                'Zone46' => null,
                'Zone47' => null,
            ];

            $mctHubConfig = [
                'zones' => $zonesMuscat,
                'zoneStateLabels' => $stateLabels,
                'zoneGovernorateLabels' => $govLabels,
                'zonePlaceMap' => $placeMap,
            ];

            $this->seedForOwner($muscatHub, $mctHubConfig);
        }
        $salalahStation = Station::find(2) ?? Hub::where('name', 'like', '%Salalah Station%')->first();


        // Salalah Station config
// ===== Salalah Station (like Sohar Station) =====
// ===== Salalah Station (like Sohar Station) =====
        $salalahStation = Station::find(2) ?? Station::where('name', 'like', '%Salalah%')->first();

        if ($salalahStation) {
            $salalahStateWKT = "MULTIPOLYGON(((54.1393 17.3904,54.1999 17.014,54.0446 16.9904,54.0221 16.9837,54.0107 16.9765,54.0015 16.9674,53.9982 16.956,53.9971 16.9565,53.9938 16.9526,53.9954 16.9499,53.9985 16.9504,53.9996 16.9487,53.9979 16.9418,53.999 16.9368,54.0021 16.9363,54.0035 16.9376,54.0071 16.9357,54.0049 16.9393,54.0074 16.9401,54.0107 16.9387,54.0107 16.9415,54.0121 16.9415,54.0112 16.9354,54.0079 16.9326,54.0074 16.9299,54.0024 16.9279,53.9993 16.9293,53.9985 16.9276,53.9938 16.929,53.9901 16.9279,53.9868 16.9246,53.9829 16.924,53.9799 16.9218,53.9799 16.9165,53.9779 16.9171,53.9765 16.9129,53.9688 16.9107,53.9685 16.9071,53.9624 16.9112,53.9493 16.909,53.9479 16.9079,53.9487 16.904,53.9468 16.8979,53.9349 16.8971,53.921 16.8999,53.9121 16.8968,53.9038 16.8962,53.904 16.8946,53.9012 16.8935,53.8907 16.8968,53.8771 16.896,53.8635 16.8935,53.8624 16.8893,53.8599 16.8904,53.8579 16.8885,53.8524 16.8888,53.8496 16.8865,53.8385 16.8862,53.8382 16.8846,53.8346 16.884,53.8332 16.886,53.8235 16.8871,53.7732 16.8782,53.769 16.8765,53.7674 16.8738,53.764 16.8746,53.7618 16.8721,53.7537 16.8704,53.7524 16.869,53.7537 16.8668,53.7526 16.8632,53.7499 16.8624,53.7449 16.856,53.7379 16.8521,53.7304 16.8429,53.7201 16.8396,53.7163 16.8337,53.7118 16.8312,53.711 16.8257,53.7015 16.8254,53.7001 16.8207,53.6926 16.8154,53.6874 16.8165,53.6832 16.8149,53.6829 16.8107,53.676 16.8062,53.6721 16.7971,53.6715 16.794,53.6754 16.7868,53.6754 16.784,53.6726 16.7804,53.6643 16.7779,53.6582 16.7782,53.6479 16.7726,53.6435 16.7721,53.6426 16.7701,53.6221 16.7651,53.6199 16.7612,53.6124 16.7618,53.6071 16.7585,53.604 16.7593,53.5946 16.7563,53.5887 16.7535,53.5857 16.7485,53.5835 16.7482,53.5779 16.7515,53.5737 16.7496,53.5668 16.7532,53.556 16.7537,53.5496 16.7574,53.536 16.7554,53.5326 16.7582,53.5299 16.7574,53.5179 16.7593,53.5124 16.7618,53.5065 16.7615,53.5037 16.7599,53.491 16.7601,53.484 16.7576,53.4774 16.7576,53.4715 16.7549,53.4688 16.756,53.4682 16.7549,53.4624 16.7551,53.4596 16.7529,53.4524 16.7543,53.4421 16.7457,53.436 16.7476,53.4165 16.7454,53.4118 16.7432,53.409 16.7399,53.4049 16.741,53.3979 16.7379,53.3907 16.7385,53.3829 16.7351,53.374 16.7363,53.3649 16.7332,53.3593 16.734,53.3407 16.7307,53.3321 16.7282,53.3243 16.7224,53.3115 16.7207,53.3079 16.7185,53.3018 16.7199,53.2954 16.719,53.2835 16.714,53.2699 16.7126,53.264 16.7101,53.2629 16.7074,53.2571 16.7038,53.2521 16.7043,53.2565 16.7026,53.256 16.7018,53.2463 16.704,53.214 16.6963,53.2093 16.6932,53.1929 16.691,53.1768 16.6793,53.1629 16.6768,53.1604 16.674,53.1579 16.6743,53.1504 16.6662,53.141 16.6657,53.1307 16.6621,53.1251 16.6576,53.1221 16.6571,53.1196 16.6535,53.1063 16.6496,53.1029 16.6465,53.0885 16.6449,53.0823 16.6424,53.0786 16.6459,53.0956 16.6494,53.107 16.6544,53.0379 16.8028,52.9704 16.7948,52.9479 16.8007,52.7126 17.0803,52.7356 17.2445,53.4917 17.3104,54.1393 17.3904)))";
            $dhankWKT = "MULTIPOLYGON(((55.5716 23.6542,56.1298 24.0038,56.3802 23.4904,55.2187 22.9033,55.2136 22.9356,55.2145 22.9442,55.2188 22.952,55.2176 22.9606,55.22 22.9783,55.2187 22.9899,55.2205 22.9945,55.2174 23.0097,55.2166 23.0262,55.2185 23.0333,55.2301 23.0479,55.2303 23.0556,55.2256 23.0724,55.2314 23.0853,55.231 23.1035,55.2365 23.119,55.252 23.1419,55.2804 23.177,55.2991 23.2069,55.3434 23.2846,55.4017 23.3923,55.4164 23.382,55.4307 23.3994,55.4369 23.4117,55.4472 23.4559,55.4495 23.46,55.4709 23.4902,55.4829 23.5118,55.4983 23.5334,55.5234 23.5548,55.5374 23.5831,55.5677 23.6186,55.5726 23.6297,55.5716 23.6542)))";
            $thumraitWKT = "MULTIPOLYGON(((53.0846 19.3622,53.4917 19.1502,54.9341 18.4048,54.6711 17.732,54.1393 17.3904,53.4917 17.3104,52.8277 17.2525,52.8123 17.2855,52.7458 17.2944,52.7437 17.3024,52.7514 17.3042,52.7506 17.3058,52.7822 17.3497,52 19,53.0846 19.3622)))";
            $taqahWKT = "MULTIPOLYGON(((54.6762 17.7088,54.5487 17.0315,54.5415 17.0299,54.5368 17.0318,54.5271 17.031,54.526 17.0329,54.5143 17.0329,54.5137 17.034,54.4535 17.0324,54.4487 17.0318,54.446 17.0301,54.4457 17.0271,54.4424 17.0263,54.4393 17.0282,54.4357 17.0362,54.4313 17.0393,54.4357 17.0329,54.4343 17.0265,54.429 17.0276,54.4287 17.0304,54.4268 17.0313,54.4096 17.0307,54.4074 17.0332,54.3462 17.0329,54.266 17.0246,54.2274 17.0173,54.1999 17.0143,54.1393 17.3904,54.6711 17.732,54.6762 17.7088)))";
            $commonPolygonSalalah = [
                [
                    [54.068, 17.008],
                    [54.180, 17.008],
                    [54.180, 17.120],
                    [54.068, 17.120],
                    [54.068, 17.008],
                ]
            ];

            $salalahZonesCount = 14;

            $zonesSalalah = [];
            for ($i = 1; $i <= $salalahZonesCount; $i++) {
                $zoneKey = 'Zone' . str_pad($i, 2, '0', STR_PAD_LEFT);

                $zonesSalalah[$zoneKey] = [
                    'type' => 'Polygon',
                    'coordinates' => $commonPolygonSalalah,
                ];

                if ($i === 3)
                    $zonesSalalah[$zoneKey] = $dhankWKT;
                if (in_array($i, [4, 5, 9, 10, 14], true))
                    $zonesSalalah[$zoneKey] = $salalahStateWKT;
                if ($i === 12)
                    $zonesSalalah[$zoneKey] = $thumraitWKT;
                if ($i === 9)
                    $zonesSalalah[$zoneKey] = $taqahWKT;
            }

            $salalahStateLabels = ['Salalah', 'Salalah', 'صلالة'];
            $dhofarGovLabels = ['Dhofar', 'ظفار'];

            $stateLabelsSalalah = [];
            $govLabelsSalalah = [];
            for ($i = 1; $i <= $salalahZonesCount; $i++) {
                $zoneKey = 'Zone' . str_pad($i, 2, '0', STR_PAD_LEFT);
                $stateLabelsSalalah[$zoneKey] = $salalahStateLabels;
                $govLabelsSalalah[$zoneKey] = $dhofarGovLabels;
            }
            // $stateLabelsSalalah['Zone03'] = ['Dhank', 'Dhank', 'ضنك'];
            // $govLabelsSalalah['Zone03'] = ['Ad Dhahirah', 'الظاهرة'];
            $stateLabelsSalalah['Zone14'] = ['Taqah', 'Taqah', 'طاقة'];
            $stateLabelsSalalah['Zone12'] = ['Thumrait', 'Thumrait', 'ثمريت'];
            $stateLabelsSalalah['Zone09'] = ['Taqah', 'Taqah', 'طاقة'];
            $stateLabelsSalalah['Zone10'] = ['Mirbat', 'Mirbat', 'مرباط'];

            $placeMapSalalah = [
                'Zone02' => [421, 'Al dharyz alshmalya'],
                'Zone03' => [423, 'Al saada alshmalya'],
                'Zone04' => [416, 'slala'],
                'Zone05' => [416, 'slala'],
                'Zone10' => [658, 'mrbat'],
                'Zone12' => [198, 'thmryt'],
                'Zone14' => [545, 'Taqa'],
            ];

            $salalahConfig = [
                'zones' => $zonesSalalah,
                'zoneStateLabels' => $stateLabelsSalalah,
                'zoneGovernorateLabels' => $govLabelsSalalah,
                'zonePlaceMap' => $placeMapSalalah,
            ];

            $this->seedForStation($salalahStation, $salalahConfig);
        }

        // === Ruwi Station ===
        $ruwiStation = Station::find(4) ?? Station::where('name', 'like', '%Ruwi Station%')->first();

        // $ruwiStation = \App\Models\Station::firstOrCreate(
        //     ['name' => 'Ruwi Station'],
        //     [
        //         'hub_id' => optional($muscatHub)->id,
        //         'contact_number' => 909098879098,
        //         'country_id' => 165,
        //         'governorate_id' => 1,
        //         'state_id' => 1,
        //         'lat' => 23.5975,
        //         'lng' => 58.5401,
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ]
        // );

        // 1) Polygon افتراضي حوالين روي — بدّله لو عندك GeoJSON/WKT أدق
        $commonPolygonRuwi = [
            [
                [58.5280, 23.5950],
                [58.5550, 23.5950],
                [58.5550, 23.6150],
                [58.5280, 23.6150],
                [58.5280, 23.5950],
            ]
        ];

        // 2) الزونات (18 زون) – كلها دلوقتي بنفس الـ Polygon الافتراضي
        $zonesRuwi = [
            'Zone01' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone02' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone03' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone04' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone05' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone06' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone07' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone08' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone09' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone10' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone11' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone12' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone13' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone14' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone15' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone16' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone17' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
            'Zone18' => ['type' => 'Polygon', 'coordinates' => $commonPolygonRuwi],
        ];

        // 3) State labels (اختيارات منطقيّة حسب المناطق في الشيت)
        $stateLabelsRuwi = [
            'Zone01' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone02' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone03' => ['As Muttrah', 'Muttrah', 'مطرح'],
            'Zone04' => ['As Al Amarat', 'Al Amarat', 'العامرات'],
            'Zone05' => ['As Muscat', 'Muscat', 'مسقط'],
            'Zone06' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone07' => ['As Muttrah', 'Muttrah', 'مطرح'],
            'Zone08' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone09' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone10' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone11' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone12' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone13' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone14' => ['As Bawshar', 'Bawshar', 'بوشر'],
            'Zone15' => ['As Bawshar', 'Bawshar', 'بوشر'],
            // زون 16 فيه أكتر من ولاية من الشرقية الجنوبية – هنحطهم سوا:
            'Zone16' => ['Al Kamil Wal Wafi', 'Jaalan Bani Bu Ali', 'Jaalan Bani Bu Hassan'],
            'Zone17' => ['Sur', 'Ṣūr {Sur}', 'صور'],
            'Zone18' => ['As Muscat', 'Muscat', 'مسقط'],
        ];

        // 4) Governorates (Muscat لمعظمها؛ 16–17 جنوب الشرقية)
        $govLabelsRuwi = [
            'Zone01' => ['Muscat', 'مسقط'],
            'Zone02' => ['Muscat', 'مسقط'],
            'Zone03' => ['Muscat', 'مسقط'],
            'Zone04' => ['Muscat', 'مسقط'],
            'Zone05' => ['Muscat', 'مسقط'],
            'Zone06' => ['Muscat', 'مسقط'],
            'Zone07' => ['Muscat', 'مسقط'],
            'Zone08' => ['Muscat', 'مسقط'],
            'Zone09' => ['Muscat', 'مسقط'],
            'Zone10' => ['Muscat', 'مسقط'],
            'Zone11' => ['Muscat', 'مسقط'],
            'Zone12' => ['Muscat', 'مسقط'],
            'Zone13' => ['Muscat', 'مسقط'],
            'Zone14' => ['Muscat', 'مسقط'],
            'Zone15' => ['Muscat', 'مسقط'],
            'Zone16' => ['South Al Sharqiyah', 'جنوب الشرقية'],
            'Zone17' => ['South Al Sharqiyah', 'جنوب الشرقية'],
            'Zone18' => ['Muscat', 'مسقط'],
        ];

        // 5) placeMap — من ملف ruwi sort rule.xlsx (عمود city)
        $placeMapRuwi = [
            'Zone01' => ['Al Khuwair'],
            'Zone02' => ['Al khuwayr north', 'Al Sarwj'],
            'Zone03' => ['Al Hamriyah', 'Al Hamriyah', 'Wadi Adi'],
            'Zone04' => ["Al Amarat", 'Al Hajar', 'Al Amarat'],
            'Zone05' => ['Al Bustan', 'Bandar Jissah', 'Qantab', 'Riyam', 'Takia', 'Yenkit', 'Yiti'],
            'Zone06' => ['Al Wutayyah', 'Wattaya'],
            'Zone07' => ['Al Wadi Al Kabir', 'Ash shutayfi', 'Darseit', 'Jibroo', 'Maṭraḥ {Matrah}', 'Muttrah', 'Ruwi'],
            'Zone08' => ['Al Ansab', 'Falaj Al Sham'],
            'Zone09' => ['Al muna', 'Baushar', 'Bawshar'],
            'Zone10' => ['Ghala'],
            'Zone11' => ['Al Ghubrah South'],
            'Zone12' => ['Al Ghubrah'],
            'Zone13' => ['Madinat Al Ilam', 'Madinat Al Sultan Qaboos'],
            'Zone14' => ['Mina Al Fahal', 'Qurum'],
            'Zone15' => ['Athaiba (Azaiba)'],
            'Zone16' => ['Al Kamil Wal Wafi', 'Jaalan Bani Bu Ali', 'Jaalan Bani Bu Hassan'],
            'Zone17' => ['Sur', 'Ṣūr {Sur}'],
            'Zone18' => ['Muscat'],
        ];

        // 6) نفس واجهة seedForStation اللي عندك
        $ruwiConfig = [
            'zones' => $zonesRuwi,
            'zoneStateLabels' => $stateLabelsRuwi,
            'zoneGovernorateLabels' => $govLabelsRuwi,
            'zonePlaceMap' => $placeMapRuwi,
        ];

        $this->seedForStation($ruwiStation, $ruwiConfig);




        foreach (range(1, 17) as $i) {
            $name = 'Zone' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $gj = $zones[$name] ?? null;

            if ($gj === null) {
                $this->command->warn("{$name}: no geometry provided — skipped.");
                continue;
            }

            $gj = $this->closeRings($gj);
            $gjStr = json_encode($gj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            DB::beginTransaction();
            try {
                $exists = DB::table('zones')->where('name', $name)->first();

                if ($exists) {
                    DB::update(
                        "UPDATE zones
                         SET owner_type=?, owner_id=?,
                             coordinates = ST_GeomFromGeoJSON(?, 1, 4326),
                             updated_at=?
                         WHERE id=?",
                        [Station::class, $station->id, $gjStr, now(), $exists->id]
                    );
                    $zoneId = (int) $exists->id;
                    $this->command->info("Updated {$name} (#{$zoneId})");
                } else {
                    DB::insert(
                        "INSERT INTO zones (name, owner_type, owner_id, coordinates, created_at, updated_at)
                         VALUES (?, ?, ?, ST_GeomFromGeoJSON(?, 1, 4326), ?, ?)",
                        [$name, Station::class, $station->id, $gjStr, now(), now()]
                    );
                    $zoneId = (int) DB::getPdo()->lastInsertId();
                    $this->command->info("Inserted {$name} (#{$zoneId})");
                }

                $labelCount = 0;
                if (!empty($zoneStateLabels[$name])) {
                    // نجيب IDs فقط ونـSYNC عليهم (Exclusive)
                    $stateIds = $this->resolveStateIdsByLabels($zoneStateLabels[$name] ?? []);

                    $labelCount = $this->syncStateZonesExclusive($zoneId, $stateIds);
                } else {
                    // لو مفيش labels محددة → امسح أي قديم
                    DB::table('state_zones')->where('zone_id', $zoneId)->delete();
                }

                $this->command->info("→ Linked {$labelCount} state(s) by labels only, for {$name}");

                $extraGovIds = [];
                if (!empty($zoneGovernorateLabels[$name])) {
                    $extraGovIds = $this->findGovernorateIdsByLabels($zoneGovernorateLabels[$name]);
                }
                $this->attachGovernoratesToZone($zoneId, $extraGovIds);
                $placeIds = $this->resolvePlaceIdsForZone($zonePlaceMap[$name] ?? []);
                $placesCount = $this->syncZonePlacesExclusive($zoneId, $placeIds);
                $this->command->info("→ Linked {$placesCount} place(s) explicitly for {$name}");



                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->command->error("Failed {$name}: " . $e->getMessage());
            }
        }

        $this->command->info('Zones 01–17 seeding via GeoJSON completed (spatial + fuzzy labels).');
    }
    private function seedForOwner($owner, array $config): void
    {
        $zones = $config['zones'] ?? [];
        $zoneStateLabels = $config['zoneStateLabels'] ?? [];
        $zoneGovernorateLabels = $config['zoneGovernorateLabels'] ?? [];
        $zonePlaceMap = $config['zonePlaceMap'] ?? [];

        $ownerType = get_class($owner);
        $ownerId = $owner->id;
        $this->command->info("Seeding for {$ownerType} #{$ownerId} ({$owner->name})");

        foreach ($zones as $name => $geomDef) {
            if ($geomDef === null) {
                $this->command->warn("{$name}: no geometry — skipped.");
                continue;
            }

            DB::beginTransaction();
            try {
                // مهم: نعمل lookup على (name + owner_type + owner_id)
                $exists = DB::table('zones')
                    ->where('name', $name)
                    ->where('owner_type', $ownerType)
                    ->where('owner_id', $ownerId)
                    ->first();

                $geomExpr = $this->geomSql($geomDef);

                if ($exists) {
                    DB::table('zones')->where('id', $exists->id)->update([
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                        'coordinates' => $geomExpr,
                        'updated_at' => now(),
                    ]);
                    $zoneId = (int) $exists->id;
                    $this->command->info("Updated {$name} (#{$zoneId})");
                } else {
                    DB::table('zones')->insert([
                        'name' => $name,
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                        'coordinates' => $geomExpr,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $zoneId = (int) DB::getPdo()->lastInsertId();
                    $this->command->info("Inserted {$name} (#{$zoneId})");
                }

                // States
                $stateIds = $this->resolveStateIdsByLabels($zoneStateLabels[$name] ?? []);
                $stateCount = $this->syncStateZonesExclusive($zoneId, $stateIds);
                $this->command->info("→ Linked {$stateCount} state(s) for {$name}");

                // Governorates
                $extraGovIds = !empty($zoneGovernorateLabels[$name] ?? [])
                    ? $this->findGovernorateIdsByLabels($zoneGovernorateLabels[$name])
                    : [];
                $this->attachGovernoratesToZone($zoneId, $extraGovIds);

                // Places (حصريًا)
                $placeIds = $this->resolvePlaceIdsForZone($zonePlaceMap[$name] ?? []);
                $placesCount = $this->syncZonePlacesExclusive($zoneId, $placeIds);
                $this->command->info("→ Linked {$placesCount} place(s) for {$name}");

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->command->error("Failed {$name}: " . $e->getMessage());
            }
        }
    }


    private function resolveStateIdsByLabels(array $labels): array
    {
        if (empty($labels))
            return [];

        $states = DB::table('states')->select('id', 'en_name', 'ar_name')->get();
        $ids = [];
        $unmatched = [];

        foreach ($labels as $label) {
            $normLabel = $this->normalizeForCompare($label);

            // دور في states على exact normalized match
            $match = $states->first(function ($s) use ($normLabel) {
                return $this->normalizeForCompare($s->en_name) === $normLabel
                    || $this->normalizeForCompare($s->ar_name) === $normLabel;
            });

            if ($match) {
                $ids[] = (int) $match->id;
            } else {
                $unmatched[] = $label;
            }
        }

        if (!empty($unmatched)) {
            $this->command->warn("Unmatched state labels (strict): " . implode(' | ', $unmatched));
        }

        return array_values(array_unique($ids));
    }


    private function syncStateZonesExclusive(int $zoneId, array $stateIds): int
    {
        DB::table('state_zones')->where('zone_id', $zoneId)->delete();

        if (empty($stateIds))
            return 0;

        $cols = collect(DB::select("SHOW COLUMNS FROM state_zones"))->pluck('Field')->all();
        $hasCreated = in_array('created_at', $cols, true);
        $hasUpdated = in_array('updated_at', $cols, true);

        $now = now();
        $rows = [];
        foreach ($stateIds as $sid) {
            $row = ['zone_id' => $zoneId, 'state_id' => (int) $sid];
            if ($hasCreated)
                $row['created_at'] = $now;
            if ($hasUpdated)
                $row['updated_at'] = $now;
            $rows[] = $row;
        }

        if (!empty($rows)) {
            DB::table('state_zones')->insert($rows);
        }
        return count($rows);
    }


    /* ===================== Helpers ===================== */

    private function ensureZoneGeometryColumn(): void
    {
        try {
            DB::statement("ALTER TABLE zones MODIFY coordinates GEOMETRY SRID 4326 NULL");
        } catch (\Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE zones ADD SPATIAL INDEX idx_zones_coordinates (coordinates)");
        } catch (\Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE zones ADD INDEX idx_zones_owner (owner_type, owner_id)");
        } catch (\Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE zones ADD COLUMN governorates_cache JSON NULL");
        } catch (\Throwable $e) {
        }
    }

    private function ensureStateGeometryColumn(): void
    {
        try {
            DB::statement("ALTER TABLE states MODIFY polygon GEOMETRY SRID 4326 NULL");
        } catch (\Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE states ADD SPATIAL INDEX idx_states_polygon (polygon)");
        } catch (\Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE states ADD INDEX idx_states_lng_lat (lng, lat)");
        } catch (\Throwable $e) {
        }
    }

    private function closeRings(array $geojson): array
    {
        $close = function (array $ring) {
            if (!$ring)
                return $ring;
            $first = $ring[0];
            $last = $ring[count($ring) - 1];
            if ($first !== $last)
                $ring[] = $first;
            return $ring;
        };
        if (($geojson['type'] ?? '') === 'Polygon') {
            $geojson['coordinates'] = array_map($close, $geojson['coordinates'] ?? []);
        } elseif (($geojson['type'] ?? '') === 'MultiPolygon') {
            $geojson['coordinates'] = array_map(fn($poly) => array_map($close, $poly), $geojson['coordinates'] ?? []);
        }
        return $geojson;
    }
    private function resolvePlaceIdsForZone(array $items): array
    {
        if (empty($items) || !Schema::hasTable('places'))
            return [];

        // حدّد أعمدة الاسم الموجودة فعلاً
        $nameCols = array_values(array_filter(['name', 'en_name', 'ar_name'], fn($c) => Schema::hasColumn('places', $c)));
        $select = array_merge(['id'], $nameCols);
        $rows = DB::table('places')->select($select)->get();

        $wantIds = [];
        $unmatched = [];

        foreach ($items as $it) {
            if (is_numeric($it)) {             // ID مباشر
                $wantIds[] = (int) $it;
                continue;
            }

            // بحث باسم صارم (normalized exact)
            $target = $this->normalizeForCompare((string) $it);
            $match = $rows->first(function ($r) use ($target, $nameCols) {
                foreach ($nameCols as $c) {
                    if ($this->normalizeForCompare((string) ($r->$c ?? '')) === $target)
                        return true;
                }
                return false;
            });

            if ($match) {
                $wantIds[] = (int) $match->id;
            } else {
                $unmatched[] = (string) $it;
            }
        }

        if (!empty($unmatched)) {
            $this->command->warn("Unmatched places (strict): " . implode(' | ', $unmatched));
        }

        return array_values(array_unique($wantIds));
    }

    private function geomSql($geom)
    {
        if (is_array($geom)) {
            $gjStr = json_encode($this->closeRings($geom), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // من GeoJSON: نحافظ على SRID ونصلّح التوبولوجي
            return DB::raw("ST_Buffer(ST_GeomFromGeoJSON('{$gjStr}', 1, 4326), 0)");
        }

        // WKT: لو المستخدم بعت MULTIPOLYGON مع بوليجون واحد، ينفع POLYGON برضه
        $wkt = trim((string) $geom);

        // Safety: لو الإحداثيات عبارة عن Polygon واحد فقط لكن ملفوفة كمولتي،
        // ممكن تشتغل زي ما هي، بس لو لسه MySQL يرفضها، ST_Buffer(…,0) هيصلّحها.
        return DB::raw("ST_Buffer(ST_GeomFromText('{$wkt}', 4326), 0)");
    }


    private function seedForStation(\App\Models\Station $station, array $config): void
    {
        $zones = $config['zones'] ?? [];
        $zoneStateLabels = $config['zoneStateLabels'] ?? [];
        $zoneGovernorateLabels = $config['zoneGovernorateLabels'] ?? [];
        $zonePlaceMap = $config['zonePlaceMap'] ?? [];

        $this->command->info("Using Station #{$station->id} ({$station->name})");

        foreach ($zones as $name => $geomDef) {
            if ($geomDef === null) {
                $this->command->warn("{$name}: no geometry provided — skipped.");
                continue;
            }

            DB::beginTransaction();
            try {
                // $exists = DB::table('zones')->where('name', $name)->first();
                $exists = DB::table('zones')
                    ->where('name', $name)
                    ->where('owner_type', \App\Models\Station::class)
                    ->where('owner_id', $station->id)
                    ->first();
                $geomExpr = $this->geomSql($geomDef);

                if ($exists) {
                    DB::table('zones')->where('id', $exists->id)->update([
                        'owner_type' => \App\Models\Station::class,
                        'owner_id' => $station->id,
                        'coordinates' => $geomExpr,
                        'updated_at' => now(),
                    ]);
                    $zoneId = (int) $exists->id;
                    $this->command->info("Updated {$name} (#{$zoneId})");
                } else {
                    DB::table('zones')->insert([
                        'name' => $name,
                        'owner_type' => \App\Models\Station::class,
                        'owner_id' => $station->id,
                        'coordinates' => $geomExpr,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $zoneId = (int) DB::getPdo()->lastInsertId();
                    $this->command->info("Inserted {$name} (#{$zoneId})");
                }

                // States
                $stateIds = $this->resolveStateIdsByLabels($zoneStateLabels[$name] ?? []);
                $labelCount = $this->syncStateZonesExclusive($zoneId, $stateIds);
                $this->command->info("→ Linked {$labelCount} state(s) by labels only, for {$name}");

                // Governorates
                $extraGovIds = !empty($zoneGovernorateLabels[$name] ?? [])
                    ? $this->findGovernorateIdsByLabels($zoneGovernorateLabels[$name])
                    : [];
                $this->attachGovernoratesToZone($zoneId, $extraGovIds);

                // Places
                $placeIds = $this->resolvePlaceIdsForZone($zonePlaceMap[$name] ?? []);
                $placesCount = $this->syncZonePlacesExclusive($zoneId, $placeIds);
                $this->command->info("→ Linked {$placesCount} place(s) explicitly for {$name}");

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->command->error("Failed {$name}: " . $e->getMessage());
            }
        }
    }



    /**
     * يربط places بالزون بشكل حصري:
     * - لو فيه pivot place_zone: نمسح روابط الزون ونضيف IDs المطلوبة فقط.
     * - لو فيه places.zone_id: نفضّي أي places مربوطة بنفس الزون ومش ضمن القائمة، ثم نربط المطلوبة.
     */
    private function syncZonePlacesExclusive(int $zoneId, array $placeIds): int
    {
        if (!Schema::hasTable('places'))
            return 0;

        $now = now();

        if (Schema::hasTable('place_zone')) {
            // احذف أي روابط قديمة لنفس الزون
            DB::table('place_zone')->where('zone_id', $zoneId)->delete();

            if (empty($placeIds))
                return 0;

            $cols = collect(DB::select("SHOW COLUMNS FROM place_zone"))->pluck('Field')->all();
            $hasCreated = in_array('created_at', $cols, true);
            $hasUpdated = in_array('updated_at', $cols, true);

            $rows = [];
            foreach ($placeIds as $pid) {
                $row = ['zone_id' => $zoneId, 'place_id' => (int) $pid];
                if ($hasCreated)
                    $row['created_at'] = $now;
                if ($hasUpdated)
                    $row['updated_at'] = $now;
                $rows[] = $row;
            }
            DB::table('place_zone')->insert($rows);
            return count($rows);
        }

        // بدون Pivot: استخدم عمود places.zone_id
        if (Schema::hasColumn('places', 'zone_id')) {
            // فك أي روابط قديمة للزون باستثناء المطلوبين
            DB::table('places')
                ->where('zone_id', $zoneId)
                ->when(!empty($placeIds), fn($q) => $q->whereNotIn('id', $placeIds))
                ->update(['zone_id' => null, 'updated_at' => $now]);

            if (!empty($placeIds)) {
                DB::table('places')->whereIn('id', $placeIds)->update(['zone_id' => $zoneId, 'updated_at' => $now]);
                return count($placeIds);
            }
            return 0;
        }

        $this->command->warn("No place_zone pivot and no places.zone_id column — cannot link places for zone #{$zoneId}.");
        return 0;
    }

    private function norm4326(string $expr): string
    {
        return "
        ST_Buffer(
            CASE
                WHEN ST_SRID($expr)=0 THEN ST_SRID($expr,4326)
                WHEN ST_SRID($expr)<>4326 THEN ST_SRID($expr,4326)
                ELSE $expr
            END, 0
        )
    ";
    }

    /** Spatial attach للولايات */
    // private function attachStatesToZone(int $zoneId): int
    // {
    //     DB::table('state_zones')->where('zone_id', $zoneId)->delete();

    //     $zGeom = $this->norm4326('(SELECT coordinates FROM zones WHERE id=' . $zoneId . ' LIMIT 1)');

    //     $sql = "
    //         SELECT DISTINCT s.id
    //         FROM states s
    //         WHERE
    //           (
    //             s.polygon IS NOT NULL
    //             AND ST_Intersects({$this->norm4326('s.polygon')}, $zGeom)
    //           )
    //           OR
    //           (
    //             s.polygon IS NULL AND s.lng IS NOT NULL AND s.lat IS NOT NULL
    //             AND ST_Contains($zGeom, ST_SRID(Point(s.lng, s.lat), 4326))
    //           )
    //         ORDER BY s.id
    //     ";
    //     $ids = array_map(fn($r) => (int) $r->id, DB::select($sql));

    //     if (empty($ids))
    //         return 0;

    //     $this->insertStateZones($zoneId, $ids);
    //     return count($ids);
    // }

    /** Label-based attach (Fuzzy) للولايات */
    // private function attachStatesToZoneByLabels(int $zoneId, array $labels, float $minScore = 0.72): int
    // {
    //     if (empty($labels))
    //         return 0;

    //     $states = DB::table('states')->select('id', 'en_name', 'ar_name')->get();
    //     $existing = DB::table('state_zones')->where('zone_id', $zoneId)->pluck('state_id')->map(fn($x) => (int) $x)->all();

    //     $toAdd = [];
    //     $unmatched = [];

    //     foreach ($labels as $label) {
    //         $matchId = $this->bestNameMatch($label, $states, $minScore);
    //         if ($matchId) {
    //             if (!in_array($matchId, $existing, true) && !in_array($matchId, $toAdd, true)) {
    //                 $toAdd[] = $matchId;
    //             }
    //         } else {
    //             $unmatched[] = $label;
    //         }
    //     }

    //     if (!empty($unmatched)) {
    //         $this->command->warn("Unmatched state labels for zone #{$zoneId}: " . implode(' | ', $unmatched));
    //     }

    //     if (!empty($toAdd)) {
    //         $this->insertStateZones($zoneId, $toAdd);
    //     }

    //     return count($toAdd);
    // }

    private function insertStateZones(int $zoneId, array $stateIds): void
    {
        $cols = collect(DB::select("SHOW COLUMNS FROM state_zones"))->pluck('Field')->all();
        $hasCreated = in_array('created_at', $cols, true);
        $hasUpdated = in_array('updated_at', $cols, true);

        $now = now();
        $rows = [];
        foreach ($stateIds as $sid) {
            $row = ['zone_id' => $zoneId, 'state_id' => (int) $sid];
            if ($hasCreated)
                $row['created_at'] = $now;
            if ($hasUpdated)
                $row['updated_at'] = $now;
            $rows[] = $row;
        }
        if (!empty($rows))
            DB::table('state_zones')->insert($rows);
    }

    /** Governorates attach من الولايات + labels إضافية (اختياري) */
    private function attachGovernoratesToZone(int $zoneId, array $extraGovIds = []): void
    {
        $govs = DB::table('governorates as g')
            ->join('states as s', 's.governorate_id', '=', 'g.id')
            ->join('state_zones as sz', 'sz.state_id', '=', 's.id')
            ->where('sz.zone_id', $zoneId)
            ->distinct()
            ->get(['g.id', 'g.en_name', 'g.ar_name']);

        // merge مع الإضافي من labels
        $finalGovIds = collect($govs)->pluck('id')->merge($extraGovIds)->unique()->values()->all();

        if (Schema::hasTable('governorate_zone')) {
            DB::table('governorate_zone')->where('zone_id', $zoneId)->delete();

            $cols = collect(DB::select("SHOW COLUMNS FROM governorate_zone"))->pluck('Field')->all();
            $hasCreated = in_array('created_at', $cols, true);
            $hasUpdated = in_array('updated_at', $cols, true);

            $now = now();
            $rows = [];
            foreach ($finalGovIds as $gid) {
                $row = ['zone_id' => $zoneId, 'governorate_id' => (int) $gid];
                if ($hasCreated)
                    $row['created_at'] = $now;
                if ($hasUpdated)
                    $row['updated_at'] = $now;
                $rows[] = $row;
            }
            if (!empty($rows))
                DB::table('governorate_zone')->insert($rows);
        }

        // كاش اختياري
        $cacheList = DB::table('governorates')->whereIn('id', $finalGovIds)->orderBy('en_name')->get(['id', 'en_name', 'ar_name']);
        try {
            DB::table('zones')->where('id', $zoneId)->update([
                'governorates_cache' => json_encode($cacheList, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
        }
    }

    /** ارجّع IDs للمحافظات بالماتش الغامض */
    private function findGovernorateIdsByLabels(array $labels, float $minScore = 0.72): array
    {
        if (empty($labels))
            return [];
        $govs = DB::table('governorates')->select('id', 'en_name', 'ar_name')->get();
        $ids = [];
        $unmatched = [];

        foreach ($labels as $label) {
            $id = $this->bestNameMatch($label, $govs, $minScore);
            if ($id && !in_array($id, $ids, true))
                $ids[] = $id;
            if (!$id)
                $unmatched[] = $label;
        }

        if (!empty($unmatched)) {
            $this->command->warn("Unmatched governorate labels: " . implode(' | ', $unmatched));
        }

        return $ids;
    }

    /** اختيار أفضل ماتش اسم (عربي/إنجليزي) */
    private function bestNameMatch(string $label, $rows, float $minScore): ?int
    {
        $target = $this->normalizeForCompare($label);
        if ($target === '')
            return null;

        $bestId = null;
        $best = 0.0;

        foreach ($rows as $r) {
            foreach ([$r->en_name ?? '', $r->ar_name ?? ''] as $cand) {
                $candN = $this->normalizeForCompare($cand);
                if ($candN === '')
                    continue;

                $score = $this->similarScore($target, $candN);
                if ($score > $best) {
                    $best = $score;
                    $bestId = (int) $r->id;
                }
                if ($best >= 0.999)
                    break 2; // ماتش طبق الأصل
            }
        }

        return ($best >= $minScore) ? $bestId : null;
    }

    private function normalizeForCompare(string $s): string
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));

        // عربي: إزالة التشكيل وتوحيد الألفات والياء والتاء المربوطة
        $s = $this->stripArabicDiacritics($s);
        $s = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $s);
        $s = str_replace(['ى'], 'ي', $s);
        $s = str_replace(['ة'], 'ه', $s);

        $s = preg_replace('/[^a-z0-9\x{0600}-\x{06FF} ]/u', '', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return $s;
    }

    private function stripArabicDiacritics(string $s): string
    {
        $dia = '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u';
        return preg_replace($dia, '', $s);
    }

    private function similarScore(string $a, string $b): float
    {
        // similar_text نسبة
        similar_text($a, $b, $p);
        $s1 = $p / 100.0;

        // Levenshtein نسبة
        $maxLen = max(mb_strlen($a, 'UTF-8'), mb_strlen($b, 'UTF-8'), 1);
        // نقرّب levenshtein باستخدام ASCII على نسخ مبسطة (آمن لمعظم الحالات)
        $al = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $a) ?: $a;
        $bl = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $b) ?: $b;
        $lev = levenshtein($al, $bl);
        $s2 = 1.0 - min($lev / $maxLen, 1.0);

        return max($s1, $s2);
    }
}
