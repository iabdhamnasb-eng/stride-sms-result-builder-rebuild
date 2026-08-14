<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result Templates</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; background: #f4f5f7; color: #1f2430; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .sub { color: #6b7280; margin: 0 0 20px; font-size: 14px; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #dc2626; color: #fff; border: 0; cursor: pointer; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #eef0f3; font-size: 14px; }
        th { background: #fafbfc; font-weight: 600; color: #4b5563; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        .empty { padding: 40px; text-align: center; color: #6b7280; }
        .flash { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
        .pagination { margin-top: 16px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Result Templates</h1>
        <p class="sub">Open a template in the visual builder to adjust how official result elements appear.</p>

        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        <div style="margin-bottom:16px;">
            <a class="btn btn-primary" href="{{ route('result-templates.create') }}">+ New Template</a>
        </div>

        @if ($templates->isEmpty())
            <div class="empty">No templates yet. Create your first one.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Paper</th>
                        <th>Orientation</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td>{{ $template->name }} @if ($template->is_default)<span title="Default template">⭐</span>@endif</td>
                            <td>{{ $template->paper_size }}</td>
                            <td>{{ ucfirst($template->orientation) }}</td>
                            <td>{{ $template->updated_at?->format('d M Y H:i') }}</td>
                            <td class="actions">
                                <a class="btn btn-primary" href="{{ route('result-templates.edit', $template) }}">Edit</a>
                                <form method="POST" action="{{ route('result-templates.destroy', $template) }}"
                                      onsubmit="return confirm('Delete this template?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="pagination">{{ $templates->links() }}</div>
        @endif
    </div>
</body>
</html>
