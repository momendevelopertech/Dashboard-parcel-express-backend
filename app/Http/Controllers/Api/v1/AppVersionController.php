<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppVersionController extends Controller
{
    public function show(Request $request)
    {
        $v = Validator::make($request->all(), [
            'platform' => 'required|in:android,ios',
            'app' => 'required|string',
            'current' => 'required|string',
            'build' => 'nullable|integer',
        ]);

        if ($v->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $v->errors()], 422);
        }

        $platform = $request->string('platform')->toString();
        $appCode = $request->string('app')->toString();
        $currentV = $request->string('current')->toString();
        $build = $request->has('build') ? (int) $request->input('build') : null;

        // دعم current بصيغة 1.1.1+27
        if (str_contains($currentV, '+') && is_null($build)) {
            [$currentV, $buildStr] = explode('+', $currentV, 2);
            $build = (int) preg_replace('/\D/', '', $buildStr);
        }
        $build = (int) ($build ?? 0);

        // قراءة القيم (ممكن تكاشيها)
        $ver = cache()->remember("appver_{$platform}_{$appCode}", 60, function () use ($platform, $appCode) {
            return AppVersion::where('platform', $platform)
                ->where('app_code', $appCode)
                ->first();
        });

        if (!$ver) {
            return response()->json([
                'message' => 'Version policy not found for given platform/app.',
            ], 404);
        }

        $cmpMin = version_compare($currentV, $ver->min_supported_version); // -1 لو أقل
        $cmpLatest = version_compare($currentV, $ver->latest_version);        // -1 لو أقل

        $mustUpdate = ($cmpMin < 0)
            || ($cmpMin === 0 && $build < (int) $ver->min_supported_build)
            || (bool) $ver->force_all;

        $shouldUpdate = false;
        if (!$mustUpdate) {
            $shouldUpdate = ($cmpLatest < 0)
                || ($cmpLatest === 0 && $build < (int) $ver->latest_build);
        }

        return response()->json([
            'platform' => $platform,
            'app' => $appCode,
            'current' => $currentV,
            'build' => $build,
            'min_supported_version' => $ver->min_supported_version,
            'min_supported_build' => (int) $ver->min_supported_build,
            'latest_version' => $ver->latest_version,
            'latest_build' => (int) $ver->latest_build,
            'must_update' => $mustUpdate,
            'should_update' => $shouldUpdate,
            'force_all' => (bool) $ver->force_all,
            'store_url' => $ver->store_url,
            'changelog' => $ver->changelog,
        ]);
    }
}
