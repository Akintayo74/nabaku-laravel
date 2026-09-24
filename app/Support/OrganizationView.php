<?php

namespace App\Support;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Organization;

final class OrganizationView
{
    public static function public(Organization $org): array
    {
        $data = array_intersect_key($org->toArray(), array_flip(['_id', 'id', 'name', 'description', 'profile_image', 'cover_image', 'type', 'createdAt', 'updatedAt']));
        $id = Ids::object((string) $org->getKey());
        $contact = Contact::where('organization', $id)->first();
        $address = Address::where('organization', $id)->first();
        $data['contact'] = $contact;
        $data['address'] = $address;

        return $data;
    }
}
