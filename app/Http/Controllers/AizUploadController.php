<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Upload;
use Response;
use Auth;
use Storage;
use Image;

class AizUploadController extends Controller
{


    public function index(Request $request){


        $all_uploads = Upload::accessibleTo($request);
        $search = null;
        $sort_by = null;

        if ($request->search != null) {
            $search = $request->search;
            $all_uploads->where('file_original_name', 'like', '%'.$request->search.'%');
        }

        $sort_by = $request->sort;
        switch ($request->sort) {
            case 'newest':
                $all_uploads->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $all_uploads->orderBy('created_at', 'asc');
                break;
            case 'smallest':
                $all_uploads->orderBy('file_size', 'asc');
                break;
            case 'largest':
                $all_uploads->orderBy('file_size', 'desc');
                break;
            default:
                $all_uploads->orderBy('created_at', 'desc');
                break;
        }

        $all_uploads = $all_uploads->paginate(60)->appends(request()->query());


        return (auth()->user()->user_type == 'seller')
            ? view('seller.uploads.index', compact('all_uploads', 'search', 'sort_by'))
            : view('backend.uploaded_files.index', compact('all_uploads', 'search', 'sort_by'));
    }

    public function create(){
        return (auth()->user()->user_type == 'seller')
            ? view('seller.uploads.create')
            : view('backend.uploaded_files.create');
    }


    public function show_uploader(Request $request){
        if (!Auth::check()) {
            Upload::guestToken($request);
        }
        return view('uploader.aiz-uploader');
    }
    public function upload(Request $request){
        $type = array(
            "jpg"=>"image",
            "jpeg"=>"image",
            "png"=>"image",
            "svg"=>"image",
            "webp"=>"image",
            "gif"=>"image",
            "mp4"=>"video",
            "mpg"=>"video",
            "mpeg"=>"video",
            "webm"=>"video",
            "ogg"=>"video",
            "avi"=>"video",
            "mov"=>"video",
            "flv"=>"video",
            "swf"=>"video",
            "mkv"=>"video",
            "wmv"=>"video",
            "wma"=>"audio",
            "aac"=>"audio",
            "wav"=>"audio",
            "mp3"=>"audio",
            "zip"=>"archive",
            "rar"=>"archive",
            "7z"=>"archive",
            "doc"=>"document",
            "txt"=>"document",
            "docx"=>"document",
            "pdf"=>"document",
            "csv"=>"document",
            "xml"=>"document",
            "ods"=>"document",
            "xlr"=>"document",
            "xls"=>"document",
            "xlsx"=>"document"
        );

        if($request->hasFile('aiz_file')){
            $upload = new Upload;
            $extension = strtolower($request->file('aiz_file')->getClientOriginalExtension());

            // Anonymous applications and explicitly selected identity documents never enter public storage.
            if (!Auth::check() || $request->input('purpose') === 'identity') {
                abort_unless(isset($type[$extension]), 422);
                $path = $request->file('aiz_file')->store('private/uploads', 'private');
                abort_unless($path, 503);
                try {
                    $upload->file_original_name = pathinfo($request->file('aiz_file')->getClientOriginalName(), PATHINFO_FILENAME);
                    $upload->extension = $extension;
                    $upload->file_name = $path;
                    $upload->user_id = Auth::id() ?: 0;
                    $upload->guest_token = Auth::check() ? null : Upload::guestToken($request);
                    $upload->type = $type[$extension];
                    $upload->file_size = $request->file('aiz_file')->getSize();
                    $upload->save();
                } catch (\Throwable $error) {
                    Storage::disk('private')->delete($path);
                    throw $error;
                }
                return response()->json((object) []);
            }

            if(isset($type[$extension])){
                $upload->file_original_name = null;
                $arr = explode('.', $request->file('aiz_file')->getClientOriginalName());
                for($i=0; $i < count($arr)-1; $i++){
                    if($i == 0){
                        $upload->file_original_name .= $arr[$i];
                    }
                    else{
                        $upload->file_original_name .= ".".$arr[$i];
                    }
                }

                $path = $request->file('aiz_file')->store('uploads/all', 'local');
                $size = $request->file('aiz_file')->getSize();

                // Return MIME type ala mimetype extension
                $finfo = finfo_open(FILEINFO_MIME_TYPE);

                // Get the MIME type of the file
                $file_mime = finfo_file($finfo, public_path($path));

                if($type[$extension] == 'image' && get_setting('disable_image_optimization') != 1){
                    try {
                        $img = Image::make($request->file('aiz_file')->getRealPath())->encode();
                        $height = $img->height();
                        $width = $img->width();
                        if($width > $height && $width > 1500){
                            $img->resize(1500, null, function ($constraint) {
                                $constraint->aspectRatio();
                            });
                        }elseif ($height > 1500) {
                            $img->resize(null, 800, function ($constraint) {
                                $constraint->aspectRatio();
                            });
                        }
                        $img->save(public_path($path));
                        clearstatcache();
                        $size = $img->filesize();

                    } catch (\Exception $e) {
                        //dd($e);
                    }
                }

                if (config('filesystems.default') == 's3') {
                    try {
                        $disk = Storage::disk('s3');
                        $contents = file_get_contents(public_path($path));
                        $options = [
                            'visibility' => 'public',
                            'ContentType' => $extension == 'svg' ? 'image/svg+xml' : $file_mime,
                        ];
                        try {
                            $stored = $disk->put($path, $contents, $options);
                        } catch (\Aws\Exception\AwsException $error) {
                            if ($error->getAwsErrorCode() !== 'AccessControlListNotSupported') {
                                throw $error;
                            }
                            // Bucket-owner-enforced buckets accept this ACL; reads use bucket policy.
                            unset($options['visibility']);
                            $options['ACL'] = 'bucket-owner-full-control';
                            $stored = $disk->put($path, $contents, $options);
                        }
                        if (!$stored) {
                            throw new \RuntimeException('Object storage write failed.');
                        }
                    } catch (\Throwable $error) {
                        // Log the service code only; SDK messages may contain credential details.
                        $code = $error instanceof \Aws\Exception\AwsException ? $error->getAwsErrorCode() : null;
                        \Illuminate\Support\Facades\Log::warning('Media object storage upload failed.', ['service_code' => $code]);
                        @unlink(public_path($path));
                        $message = 'File upload failed. Please check the object storage configuration or try again later.';
                        if ($code === 'InvalidAccessKeyId' || $code === 'SignatureDoesNotMatch') {
                            $message = 'S3 authentication failed. Please update the AWS Access Key ID and Secret Access Key in File System settings.';
                        } elseif ($code === 'AccessDenied') {
                            $message = 'S3 denied the upload. Please check the upload permissions and bucket policy.';
                        } elseif ($code === 'AccessControlListNotSupported') {
                            $message = 'This S3 bucket does not support object ACLs. Please check the bucket upload configuration.';
                        }
                        return response()->json(['success' => false, 'code' => 'storage_upload_failed', 'message' => $message], 503);
                    }
                    if($arr[0] != 'updates') {
                        unlink(public_path($path));
                    }
                }

                $upload->extension = $extension;
                $upload->file_name = $path;
                $upload->user_id = Auth::check()?Auth::user()->id:0;
                if (!Auth::check()) {
                    $upload->guest_token = Upload::guestToken($request);
                }
                $upload->type = $type[$upload->extension];
                $upload->file_size = $size;
                $upload->save();
            }
            return response()->json((object) []);
        }
    }

    public function get_uploaded_files(Request $request)
    {
        $uploads = Upload::accessibleTo($request);
        if ($request->input('purpose') === 'identity') {
            $uploads->where('file_name', 'like', 'private/uploads/%');
        }
        if ($request->search != null) {
            $uploads->where('file_original_name', 'like', '%'.$request->search.'%');
        }
        if ($request->sort != null) {
            switch ($request->sort) {
                case 'newest':
                    $uploads->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $uploads->orderBy('created_at', 'asc');
                    break;
                case 'smallest':
                    $uploads->orderBy('file_size', 'asc');
                    break;
                case 'largest':
                    $uploads->orderBy('file_size', 'desc');
                    break;
                default:
                    $uploads->orderBy('created_at', 'desc');
                    break;
            }
        }
        return $uploads->paginate(60)->appends(request()->query());
    }

    public function destroy(Request $request,$id)
    {
        $upload = Upload::accessibleTo($request)->findOrFail($id);
        if ($upload->isPrivate()) {
            abort_unless(Storage::disk('private')->delete($upload->file_name), 503);
            $upload->delete();
            flash(translate('File deleted successfully'))->success();
            return back();
        }
        try{
            if(env('FILESYSTEM_DRIVER') == 's3'){
                Storage::disk('s3')->delete($upload->file_name);
                if (file_exists(public_path().'/'.$upload->file_name)) {
                    unlink(public_path().'/'.$upload->file_name);
                }
            }
            else{
                unlink(public_path().'/'.$upload->file_name);
            }
            $upload->delete();
            flash(translate('File deleted successfully'))->success();
        }
        catch(\Exception $e){
            $upload->delete();
            flash(translate('File deleted successfully'))->success();
        }
        return back();
    }

    public function get_preview_files(Request $request){
        $ids = explode(',', $request->ids);
        $files = Upload::readableBy($request)->whereIn('id', $ids)->get();
        $new_file_array = [];
        foreach($files as $file){
            $file['file_name'] = $file->url;
            $new_file_array[] = $file;
        }
        // dd($new_file_array);
        return $new_file_array;
        // return $files;
    }

    //Download project attachment
    public function attachment_download(Request $request, $id)
    {
        $project_attachment = Upload::readableBy($request)->findOrFail($id);
        if ($project_attachment->isPrivate()) {
            return $this->privateResponse($project_attachment, true);
        }
        try{
           $file_path = public_path($project_attachment->file_name);
            return Response::download($file_path, null, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'CDN-Cache-Control' => 'no-store',
            ])->setPrivate();
        }catch(\Exception $e){
            flash(translate('File does not exist!'))->error();
            return back();
        }

    }

    public function private_file(Request $request, $id)
    {
        $file = Upload::readableBy($request)->findOrFail($id);
        abort_unless($file->isPrivate(), 404);
        return $this->privateResponse($file);
    }

    private function privateResponse(Upload $file, $download = false)
    {
        $path = Storage::disk('private')->path($file->file_name);
        abort_unless(is_file($path), 404);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $headers = ['Cache-Control' => 'private, no-store, max-age=0', 'CDN-Cache-Control' => 'no-store',
            'Cloudflare-CDN-Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox"];
        if (!$download && in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return response()->file($path, $headers)->setPrivate();
        }
        return response()->download($path, basename($file->file_original_name).'.'.$file->extension, $headers)->setPrivate();
    }
    //Download project attachment
    public function file_info(Request $request)
    {
        $file = Upload::accessibleTo($request)->findOrFail($request['id']);

        return (auth()->user()->user_type == 'seller')
            ? view('seller.uploads.info',compact('file'))
            : view('backend.uploaded_files.info',compact('file'));
    }

}
