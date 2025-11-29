<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $jobs = Job::latest()->with( [ 'employer', 'tags' ] )->get()->groupBy( 'featured' );

        return view( 'jobs.index', [
            'jobs'          => $jobs[ 0 ],
            'featured_jobs' => $jobs[ 1 ],
            'tags'          => Tag::all(),
        ] );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        return view( 'jobs.create' );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( Request $request ) {
        $attrs = $request->validate(
            [
                'title'    => 'required|string|max:255',
                'salary'   => 'nullable|numeric|min:0',
                'location' => 'required|string|max:255',
                'schedule' => 'required|in:Full-time,Part-time,Contract,Internship',
                'url'      => 'required|url',
                'tags'     => 'nullable',
            ]
        );

        $attrs[ 'featured' ] = $request->has( 'featured' );

        $job = Auth::user()->employer->jobs()->create( Arr::except( $attrs, 'tags' ) );

        if ( isset( $attrs[ 'tags' ] ) ) {
            foreach ( explode( ',', $attrs[ 'tags' ] ) as $tag ) {
                $job->tag( $tag );
            }
        }

        return redirect( '/' );
    }

    /**
     * Display the specified resource.
     */
    public function show( Job $job ) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Job $job ) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( UpdateJobRequest $request, Job $job ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Job $job ) {
        //
    }
}
