<?php

/**
 * CRUD operation class file.
 * php version 8.3
 *
 * @category  App\Actions
 * @package   App\Actions\Idrivee2ImageUploadActions
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @version   GIT: <git_id>
 * @link      https://github.com/qurbanullah
 */
namespace App\Actions\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Interfaces\Uploadable;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions
 * @package  App\Actions\Idrivee2ImageUploadActions
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class Idrivee2ImageUploadActions
{
    /**
     * Read Data.
     *
     * @param $path  destination path to store file
     * @param $image local complete path of file to be stored
     * @param $name  name of the file by which file to be stored
     *
     * @return string file path of the stored image
     */
    public function upload(string $path, string $image, string $name): String
    {
        // dd($path, $image, $name);
        // Validator::make(
        //     $image,
        //     [
        //         'image' => 'required',
        //     ],
        //     [
        //         'image.required' => 'Image is required',
        //     ]
        // )->validate();

        // Save image to S3
        return Storage::disk('idrivee2')->putFileAs($path, $image, $name);
    }
}
