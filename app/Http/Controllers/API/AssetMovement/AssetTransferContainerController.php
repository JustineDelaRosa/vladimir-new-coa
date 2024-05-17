<?php

namespace App\Http\Controllers\API\AssetMovement;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetMovement\AssetTransfer\CreateAssetTransferContainerRequest;
use App\Models\AssetMovementContainer\AssetTransferContainer;
use App\Models\AssetTransferApprover;
use App\Models\FixedAsset;
use App\Models\TransferApproval;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class AssetTransferContainerController extends Controller
{

    use ApiResponse, AssetTransferContainerHandler;


    public function index()
    {
        $createdById = auth('sanctum')->user()->id;
        $requestTransferContainer = AssetTransferContainer::where('created_by_id', $createdById)
            //            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->setContainerResponse($requestTransferContainer);
    }


    public function store(CreateAssetTransferContainerRequest $request)
    {
        try {
            DB::beginTransaction();

            $fixedAssetId = $request->fixed_asset_id;
            $newAccountable = $request->accountable;
            $company = $request->company_id;
            $businessUnit = $request->business_unit_id;
            $department = $request->department_id;
            $unit = $request->unit_id;
            $subunit = $request->subunit_id;
            $location = $request->location_id;
            $remarks = $request->remarks;
            $description = $request->description;
            $attachments = $request->attachments;
            $createdBy = auth()->user()->id;

            $transferApprovals = AssetTransferApprover::where('subunit_id', $subunit)
                ->orderBy('layer', 'asc')
                ->get();

            $this->checkDifferentCOA($request);
            list($isRequesterApprover, $isLastApprover, $requesterLayer) = $this->checkIfRequesterIsApprover($createdBy, $transferApprovals);

            $transferContainer = AssetTransferContainer::create([
                'status' => $isLastApprover
                    ? 'Approved'
                    : ($isRequesterApprover
                        ? 'For Approval of Approver ' . ($requesterLayer + 1)
                        : 'For Approval of Approver 1'),
                'created_by_id' => $createdBy,
                'fixed_asset_id' => $fixedAssetId,
                'accountable' => $newAccountable,
                'company_id' => $company,
                'business_unit_id' => $businessUnit,
                'department_id' => $department,
                'unit_id' => $unit,
                'subunit_id' => $subunit,
                'location_id' => $location,

                'remarks' => $remarks,
                'description' => $description,
            ]);

            if ($attachments) {
                $attachments = is_array($attachments) ? $attachments : [$attachments];
                foreach ($attachments as $attachment) {
                    $transferContainer->addMedia($attachment)->toMediaCollection('attachments');
                }
            }

            DB::commit();

            return $this->responseCreated('Asset Transfer Container Created');
        } catch (FileDoesNotExist $e) {
            DB::rollBack();
            return $this->responseUnprocessable('File does not exist');
        } catch (FileIsTooBig $e) {
            DB::rollBack();
            return $this->responseUnprocessable('File is too big');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e;
        }
    }

    public function show($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id = null)
    {
        $user = auth()->user()->id;
        if ($id) {
            $container = AssetTransferContainer::where('created_by_id', $user)->find($id);

            if (!$container) {
                return $this->responseNotFound('Asset Transfer Container not found');
            }
            $container->clearMediaCollection('attachments');
            $container->delete();

            return $this->responseSuccess('Item successfully deleted');
        } else {
            $containers = AssetTransferContainer::where('created_by_id', $user)->get();
            foreach ($containers as $container) {
                $container->clearMediaCollection('attachments');
                $container->delete();
            }

            return $this->responseSuccess('Asset Transfer Containers Deleted');
        }
    }
}


/*
public function store(CreateAssetTransferContainerRequest $request)
{
    $fixedAssetId = $request->fixed_asset_id;
    $newAccountable = $request->accountable;
    $company = $request->company_id;
    $businessUnit = $request->business_unit_id;
    $department = $request->department_id;
    $unit = $request->unit_id;
    $subunit = $request->subunit_id;
    $location = $request->location_id;
    $remarks = $request->remarks;
    $attachments = $request->attachments;
    $createdBy = auth()->user()->id;

    $fixedAsset = FixedAsset::find($fixedAssetId);
    $container = new AssetTransferContainer();

    // Get the properties of $fixedAsset as an array
    $fixedAssetArray = $fixedAsset->toArray();
    unset($fixedAssetArray['id']);
    // Modify the properties as needed
    $fixedAssetArray['accountable'] = $newAccountable;
    $fixedAssetArray['company_id'] = $company;
    $fixedAssetArray['business_unit_id'] = $businessUnit;
    $fixedAssetArray['department_id'] = $department;
    $fixedAssetArray['unit_id'] = $unit;
    $fixedAssetArray['subunit_id'] = $subunit;
    $fixedAssetArray['location_id'] = $location;
    $fixedAssetArray['remarks'] = $remarks;
    $fixedAssetArray['created_by_id'] = $createdBy;
    $fixedAssetArray['fixed_asset_id'] = $fixedAssetId;

    // Assign the modified properties to $container
    $container->fill($fixedAssetArray);

    // Save the new container
    $container->save();

    // Attach the media to the container im using spatie media library
    try {
        if ($attachments) {
            $container->addMedia($attachments)->toMediaCollection('attachments');
        }
    } catch (FileDoesNotExist $e) {
        return $this->responseUnprocessable('File does not exist');
    } catch (FileIsTooBig $e) {
        return $this->responseUnprocessable('File is too big');
    }

    return $this->responseCreated('Asset Transfer Container Created', $container);
}
**/
