<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Seller Categories Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #5C352C;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #5C352C;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0;
        }
        .summary {
            margin-bottom: 30px;
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 8px;
            vertical-align: top;
        }
        .category-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .category-title {
            background: #5C352C;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: bold;
        }
        .subcategory {
            margin-bottom: 20px;
            margin-left: 20px;
        }
        .subcategory-title {
            background: #956959;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: bold;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .products-table th {
            background: #f0f0f0;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        .products-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .no-products {
            color: #999;
            font-style: italic;
            padding: 10px;
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .stats-box {
            display: inline-block;
            margin-right: 20px;
            padding: 5px 10px;
            background: #e9ecef;
            border-radius: 5px;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Seller Categories Report</h1>
        <p>Generated on: {{ $generated_date }}</p>
        <p>Seller: {{ $seller->name }} ({{ $seller->email }})</p>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td width="25%"><strong>Total Categories:</strong> {{ $total_categories }}</td>
                <td width="25%"><strong>Total Subcategories:</strong> {{ $total_subcategories }}</td>
                <td width="25%"><strong>Total Products:</strong> {{ $total_products }}</td>
                <td width="25%"><strong>Total Revenue:</strong> Tsh {{ number_format($total_revenue, 2) }}</td>
            </tr>
        </table>
    </div>

    @foreach($categories as $category)
        <div class="category-section">
            <div class="category-title">
                {{ $category->name }}
                <span style="float: right; font-size: 11px;">
                    {{ $category->total_subcategories }} Subcategories | 
                    {{ $category->total_products }} Products | 
                    Tsh {{ number_format($category->total_revenue, 2) }}
                </span>
            </div>
            
            @foreach($category->children as $subcategory)
                <div class="subcategory">
                    <div class="subcategory-title">
                        {{ $subcategory->name }}
                        <span style="float: right; font-size: 10px;">
                            {{ $subcategory->products_count }} Products | 
                            Tsh {{ number_format($subcategory->total_revenue, 2) }}
                        </span>
                    </div>
                    
                    @if($subcategory->products->count() > 0)
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product Name</th>
                                    <th>Price (Tsh)</th>
                                    <th>Stock</th>
                                    <th>Sales</th>
                                    <th>Views</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subcategory->products as $index => $product)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $product->name }}</td>
                                        <td class="text-right">{{ number_format($product->price, 2) }}</td>
                                        <td class="text-right">{{ $product->quantity }}</td>
                                        <td class="text-right">{{ $product->sales_count ?? 0 }}</td>
                                        <td class="text-right">{{ $product->views_count ?? 0 }}</td>
                                        <td class="text-right">{{ number_format($product->price * ($product->sales_count ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="no-products">No products in this subcategory</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="footer">
        <p>This report was generated automatically by U-Connect System</p>
        <p>© {{ date('Y') }} U-Connect - All Rights Reserved</p>
    </div>
</body>
</html>