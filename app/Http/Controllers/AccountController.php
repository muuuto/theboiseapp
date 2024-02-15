<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Slogan;

class AccountController extends Controller
{
    // Show all listings
    public function index()
    {
        $user = auth()->user();

        if (!is_null($user->wallet)) {
            $walletName = json_decode($user->wallet, true)['name'];
            $walletMedia = json_decode($user->wallet, true)['path'];
        } else {
            $walletName = null;
            $walletMedia = null;
        }

        return view('account.index', [
            'user' => $user,
            'walletName' => $walletName,
            'walletMedia' => $walletMedia
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $formFields = $request->validate([
            'profilePicture' => 'image|nullable',
            'name' => 'string|nullable',
        ]);

        if ($request->hasFile('profilePicture')) {
            $resizedImage = $this->resizeImage($request->file('profilePicture')->path(), 144, 144);
            $image = $this->imageConvert($resizedImage, 100);

            if (is_null($user->profilePicture)) {
                $formFields['profilePicture'] = $image->store('profilePic', 'public');
            } else {
                Storage::disk('public')->delete($user->profilePicture);
                $formFields['profilePicture'] = $image->store('profilePic', 'public');
            }
        }

        if ($request->has('name')) {
            if ($user->name != $formFields['name']) {
                if (User::where('name', '=', ($formFields['name']))->exists()) {
                    return redirect('/account')->with('message', 'Account update failed - username already taken!');
                } else {
                    Slogan::where('author', '=', ($user->name))
                        ->update(['author' => $formFields['name']]);
                }
            }
        }

        if (!$request->has('name') && !$request->hasFile('profilePicture')) {
            return redirect('/account')->with('message', 'Account update failed');
        }

        $user->update($formFields);
        return redirect('/account')->with('message', 'Account updated successfully!');
    }

    public function linkNoImage(Request $request)
    {
        $users = User::all();

        $formFields = $request->validate([
            'profilePicture' => 'required',
        ]);

        foreach ($users as $user) {
            if ($request->hasFile('profilePicture')) {
                $formFields['profilePicture'] = $request->file('profilePicture')->store('profilePic', 'public');
            }
            $user->update($formFields);
        }
        return redirect('/account')->with('message', 'Accounts updated successfully!');
    }

    public function imageConvert($file, $compression_quality)
    {
        // check if file exists
        if (!file_exists($file)) {
            return false;
        }

        // If output file already exists return path
        $output_file = $file . '.webp';
        $fileName = basename($file . '.webp');
        if (file_exists($output_file)) {
            return $output_file;
        }

        $file_type = exif_imagetype($file);

        if (function_exists('imagewebp')) {

            switch ($file_type) {
                case '1': //IMAGETYPE_GIF
                    $image = imagecreatefromgif($file);
                    break;
                case '2': //IMAGETYPE_JPEG
                    $image = imagecreatefromjpeg($file);
                    break;
                case '3': //IMAGETYPE_PNG
                    $image = imagecreatefrompng($file);
                    break;
                case '6': // IMAGETYPE_BMP
                    $image = imagecreatefrombmp($file);
                    break;
                case '15': //IMAGETYPE_Webp
                    return false;
                    break;
                case '16': //IMAGETYPE_XBM
                    $image = imagecreatefromxbm($file);
                    break;
                default:
                    return false;
            }

            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            // Save the image
            $result = imagewebp($image, $output_file, $compression_quality);
            if (false === $result) {
                return false;
            }

            // Free up memory
            imagedestroy($image);
            return new UploadedFile($output_file, $fileName, 'image/webp');
        } elseif (class_exists('Imagick')) {
            $image = new Imagick();
            $image->readImage($file);

            if ($file_type === 'png') {
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($compression_quality);
                $image->setOption('webp:lossless', 'true');
            }

            $image->writeImage($output_file);
            return new UploadedFile($output_file, $fileName, 'image/webp');
        }

        return false;
    }


    public function resizeImage($sourceImage, $maxWidth, $maxHeight, $quality = 100)
    {
        $file_type = exif_imagetype($sourceImage);
        $output_file = $sourceImage . '.jpg';
        $fileName = basename($sourceImage . '.jpg');

        switch ($file_type) {
            case '1': //IMAGETYPE_GIF
                $image = imagecreatefromgif($sourceImage);
                break;
            case '2': //IMAGETYPE_JPEG
                $image = imagecreatefromjpeg($sourceImage);
                break;
            case '3': //IMAGETYPE_PNG
                $image = imagecreatefrompng($sourceImage);
                break;
            case '6': // IMAGETYPE_BMP
                $image = imagecreatefrombmp($sourceImage);
                break;
            case '15': //IMAGETYPE_Webp
                return false;
                break;
            case '16': //IMAGETYPE_XBM
                $image = imagecreatefromxbm($sourceImage);
                break;
            default:
                return false;
        }

        $file_type != '3' ?? $exif = exif_read_data($sourceImage);

        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 8:
                    $image = imagerotate($image, 90, 0);
                    break;
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;
                case 6:
                    $image = imagerotate($image, -90, 0);
                    break;
            }
        }

        // Get dimensions of source image.
        $origWidth = (imagesx($image));
        $origHeight = imagesy($image);

        if ($maxWidth == 0) {
            $maxWidth  = $origWidth;
        }

        if ($maxHeight == 0) {
            $maxHeight = $origHeight;
        }

        // Calculate ratio of desired maximum sizes and original sizes.
        $widthRatio = $maxWidth / $origWidth;
        $heightRatio = $maxHeight / $origHeight;

        // Ratio used for calculating new image dimensions.
        $ratio = min($widthRatio, $heightRatio);

        // Calculate new image dimensions.
        $newWidth  = (int)$origWidth  * $ratio;
        $newHeight = (int)$origHeight * $ratio;

        // Create final image with new dimensions.
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagejpeg($newImage, $output_file, $quality);

        // Free up the memory.
        imagedestroy($image);
        imagedestroy($newImage);

        return new UploadedFile($output_file, $fileName, 'image/jpg');
    }

    public function addWallet(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'mediaName' => 'string',
            'addWallet' => 'image',
        ]);

        if ($request->file('addWallet')->getSize() < 5242880) {
            if (is_null($user->wallet)) {
                $formFields['wallet'] = [
                    'name' => $request["mediaName"],
                    'path' => $request->file('addWallet')->store('wallet', 'public')
                ];
            } else {
                $wallet = json_decode($user->wallet, true);
                Storage::disk('public')->delete($wallet['path']);
                $formFields['wallet'] = [
                    'name' => $request["mediaName"],
                    'path' => $request->file('addWallet')->store('wallet', 'public')
                ];
            }

            $user->update($formFields);
            return redirect('/account')->with('message', 'Wallet added to profile');
        } else {
            return redirect('/account')->with('message', 'Error, file size greater than 3Mb');
        }
    }
}
