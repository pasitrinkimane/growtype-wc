<div {!! $classes !!}>
    <div class="table-body-cell">
        <div class="e-img" style="{!! growtype_get_featured_image_tag(get_post()) !!}"></div>
    </div>
    <div class="table-body-cell">
        {!! $product->get_title() !!}
    </div>
    <div class="table-body-cell">
        <a class="e-title e-heading" href="{!! get_permalink($product->get_id()) !!}">{!! $product->get_title() !!}</a>
        <div class="e-details e-description">
            <span>{!! Growtype_Wc_Product::amount_in_units_formatted() !!}</span>
            <span class="e-separator">•</span>
            <span>{!! Growtype_Wc_Product::volume_formatted() !!}</span>
        </div>
    </div>
    <div class="table-body-cell">
        <a href="{!! get_permalink($product->get_id()) !!}" class="btn btn-primary">
            {!! __('View', 'growtype-wc') !!}
        </a>
    </div>
</div>
