{{-- Field bersama Supplier & Klien: nama, no_hp, alamat, keterangan --}}
<div class="mb-3">
    <label for="nama" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" id="nama" name="nama" value="{{ old('nama', $model->nama) }}" maxlength="150"
           class="form-control @error('nama') is-invalid @enderror" placeholder="{{ $placeholderNama ?? '' }}" required autofocus>
    @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="no_hp" class="form-label">No. HP</label>
    <input type="text" id="no_hp" name="no_hp" value="{{ old('no_hp', $model->no_hp) }}" maxlength="30" inputmode="tel"
           class="form-control @error('no_hp') is-invalid @enderror" placeholder="08xxxxxxxxxx">
    @error('no_hp') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="alamat" class="form-label">Alamat</label>
    <textarea id="alamat" name="alamat" rows="2" class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $model->alamat) }}</textarea>
    @error('alamat') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-4">
    <label for="keterangan" class="form-label">Keterangan</label>
    <textarea id="keterangan" name="keterangan" rows="2" class="form-control @error('keterangan') is-invalid @enderror">{{ old('keterangan', $model->keterangan) }}</textarea>
    @error('keterangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
